// フォームの欄を JSON に書き出す確認スクリプト。フォームの作り替えの前後（main とブランチ）を diff で比べるために使う。
//   <env> NODE_PATH=<作業用>/node_modules node .claude/skills/adaptive-validation-runner/scripts/form-snapshot.js \
//     --path 'index.php?a=17' --out <JSON> [--shots <ディレクトリ>] [--fill <JSON>] [--submit <セレクター>] [--width 1280] [--user admin]
//
// 引数:
//   --path    manager からの相対 URL（必須）
//   --out     書き出す JSON のパス
//   --shots   最初の .tab-pane のタブごとの画面写真（tab<番号>.png）の置き場所。--fill より前（開いた直後）に撮る
//   --fill    {"名前": "値"} の JSON ファイル、または JSON 文字列。DOM へ直接入れて input と change を起こす
//             （隠れたタブや行の欄にも入る）。ラジオは同じ名前で値が合うものを選ぶ。チェックボックスは値が空でなければ入れ、
//             空なら外す。文字の欄・select・hidden・textarea は同じ名前の欄が複数あっても最初の 1 つだけに入れる
//   --submit  --fill の後に押すセレクター。確認ダイアログは受け入れ、<dialog> の警告は閉じ、遷移が落ち着くのを待つ。
//             --out は着いたページを書き出す
//   --width   ビューポートの幅（既定 1280）
//   --user    ログイン名（既定は管理者）。パスワードは管理者と同じ
//   --sql     ログインの後、ページを開く前に DB へ流す SQL（キャッシュも消す）。ログイン画面に影響する設定
//             （use_captcha=1 など）を変えた状態で書き出すときに使う
//
// 出力: {"url","tabs","tabRow","fields"}。fields は form の中の名前のある欄を DOM の順に 1 件 1 行で並べる。
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { config, login, clickAlertOk, sql, appExec } = require('./lib');

function parseArgs(argv) {
    const opts = { width: 1280 };
    for (let i = 0; i < argv.length; i++) {
        const m = /^--(path|out|shots|fill|submit|width|user|sql)$/.exec(argv[i]);
        if (!m) {
            throw new Error(`不明な引数: ${argv[i]}`);
        }
        if (i + 1 >= argv.length) {
            throw new Error(`${argv[i]} に値が要る`);
        }
        opts[m[1]] = argv[++i];
    }
    if (!opts.path) {
        throw new Error('--path が要る');
    }
    opts.width = parseInt(opts.width, 10) || 1280;
    return opts;
}

function readFill(arg) {
    const text = arg.trim().startsWith('{') ? arg : fs.readFileSync(arg, 'utf8');
    return JSON.parse(text);
}

// ページの中で動かす: 欄の一覧を取る
function collect() {
    const forms = Array.from(document.forms);
    const form = forms.find(f => f.querySelector('.tab-pane')) || forms.find(f => f.closest('#mainPane')) || forms[0];
    if (!form) {
        return { url: location.href, tabs: [], tabRow: [], fields: [], error: 'form が無い' };
    }
    const pane = document.querySelector('.tab-pane');
    const tabEls = pane ? Array.from(pane.querySelectorAll(':scope > .tab-row .tab')) : [];
    const text = el => (el.textContent || '').replace(/\s+/g, ' ').trim();
    const hidden = (el, stop) => {
        for (let n = el; n && n !== stop && n.nodeType === 1; n = n.parentElement) {
            if (getComputedStyle(n).display.includes('none')) {
                return true;
            }
        }
        return false;
    };
    const fields = [];
    for (const el of form.querySelectorAll('input[name], select[name], textarea[name], button[name]')) {
        const tag = el.tagName.toLowerCase();
        const page = el.closest('.tab-page');
        let tr = el.closest('tr');
        while (tr && !tr.closest('table').classList.contains('settings')) {
            tr = tr.parentElement ? tr.parentElement.closest('tr') : null;
        }
        const cell = tr ? tr.querySelector('th, td') : null;
        const rowText = cell ? ((cell.innerText || cell.textContent || '').split('\n').map(s => s.trim()).find(s => s) || '') : '';
        const f = {
            tab: page && page.tabPage && page.tabPage.tab ? text(page.tabPage.tab) : '',
            row: rowText,
            name: el.name,
            tag,
            type: tag === 'select' ? (el.multiple ? 'select-multiple' : 'select-one') : (el.type || tag),
            value: el.name === 'csrf_token' ? '<masked>' : el.value,
            checked: !!el.checked,
            selected: tag === 'select' ? Array.from(el.options).map(o => [o.value, o.text]) : null,
            disabled: !!el.disabled,
            visible: !hidden(el, page || form.parentElement),
        };
        fields.push(f);
    }
    const tabRow = tabEls.map(t => {
        const r = t.getBoundingClientRect();
        return { text: text(t), x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) };
    });
    return { url: location.href, tabs: tabEls.map(text), tabRow, fields };
}

// ページの中で動かす: --fill の値を入れる。入れられなかった名前を返す
function fillValues(values) {
    const missing = [];
    const fire = el => {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const form = Array.from(document.forms).find(f => f.querySelector('.tab-pane')) || document.forms[0];
    for (const [name, raw] of Object.entries(values)) {
        const value = String(raw);
        const els = Array.from(form.elements).filter(e => e.name === name);
        if (!els.length) {
            missing.push(name);
            continue;
        }
        const first = els[0];
        if (first.type === 'radio') {
            const hit = els.find(e => e.type === 'radio' && e.value === value);
            if (!hit) {
                missing.push(`${name}=${value}`);
                continue;
            }
            hit.checked = true;
            fire(hit);
        } else if (first.type === 'checkbox') {
            first.checked = value !== '';
            if (value !== '') {
                first.value = value;
            }
            fire(first);
        } else if (first.tagName === 'SELECT') {
            const opt = Array.from(first.options).find(o => o.value === value);
            if (!opt) {
                missing.push(`${name}=${value}`);
                continue;
            }
            first.value = value;
            fire(first);
        } else {
            first.value = value;
            fire(first);
        }
    }
    return missing;
}

function writeJson(file, data) {
    const lines = [
        '{',
        `"url": ${JSON.stringify(data.url)},`,
        `"tabs": ${JSON.stringify(data.tabs)},`,
        `"tabRow": [`,
        data.tabRow.map(t => '  ' + JSON.stringify(t)).join(',\n'),
        '],',
        '"fields": [',
        data.fields.map(f => '  ' + JSON.stringify(f)).join(',\n'),
        ']',
        '}',
        '',
    ];
    fs.mkdirSync(path.dirname(path.resolve(file)), { recursive: true });
    fs.writeFileSync(file, lines.join('\n'));
}

(async () => {
    const opts = parseArgs(process.argv.slice(2));
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await login(browser, opts.user || config.adminUser);
        if (opts.sql) {
            sql(opts.sql);
            appExec(['php', 'evo', 'cache:clear']);
        }
        await page.setViewportSize({ width: opts.width, height: 900 });
        page.on('dialog', d => d.accept().catch(() => {}));
        await page.goto(config.manager + opts.path);
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForSelector('form', { state: 'attached', timeout: 15000 });

        if (opts.shots) {
            fs.mkdirSync(opts.shots, { recursive: true });
            const tabs = page.locator('.tab-pane').first().locator(':scope > .tab-row .tab');
            const n = await tabs.count();
            for (let i = 0; i < n; i++) {
                await tabs.nth(i).click();
                // 管理画面の本文は画面の高さの中でスクロールするので、開いたタブの高さに合わせて撮る
                const need = await page.evaluate(() => {
                    const open = document.querySelector('.tab-pane .tab-page.selected, .tab-pane .tab-page[aria-hidden="false"]');
                    return Math.max(document.documentElement.scrollHeight, open ? open.scrollHeight + 300 : 0);
                });
                await page.setViewportSize({ width: opts.width, height: Math.min(Math.max(need, 900), 8000) });
                await page.waitForTimeout(300);
                await page.locator('.tab-pane').first().screenshot({ path: path.join(opts.shots, `tab${i}.png`) });
            }
            await page.setViewportSize({ width: opts.width, height: 900 });
            if (n) {
                await tabs.nth(0).click();
            }
        }

        if (opts.fill) {
            const missing = await page.evaluate(fillValues, readFill(opts.fill));
            if (missing.length) {
                console.log(`  [fill] 入れられなかった: ${missing.join(', ')}`);
            }
        }

        if (opts.submit) {
            await page.evaluate(() => Array.from(document.forms).forEach(f => { f.dataset.snapOld = '1'; }));
            await page.click(opts.submit);
            const until = Date.now() + 30000;
            while (Date.now() < until) {
                if (await clickAlertOk(page)) {
                    continue;
                }
                const replaced = await page.evaluate(() => !document.querySelector('form[data-snap-old]')).catch(() => true);
                if (replaced) {
                    break;
                }
                await page.waitForTimeout(300);
            }
            await page.waitForLoadState('networkidle').catch(() => {});
            await page.waitForTimeout(500);
        }

        const data = await page.evaluate(collect);
        if (data.error) {
            console.log(`  [warn] ${data.error}`);
        }
        if (opts.out) {
            writeJson(opts.out, data);
        }
        console.log(`url=${data.url} tabs=${data.tabs.length} fields=${data.fields.length}${opts.out ? ' -> ' + opts.out : ''}`);
    } finally {
        await browser.close();
    }
})().catch(e => {
    console.error('FAIL', e.message);
    process.exit(1);
});
