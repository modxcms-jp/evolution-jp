// フォームの欄を JSON に書き出す確認スクリプト。フォームの作り替えの前後（main とブランチ）を diff で比べるために使う。
//   <env> NODE_PATH=<作業用>/node_modules node .claude/skills/adaptive-validation-runner/scripts/form-snapshot.js \
//     --path 'index.php?a=17' --out <JSON> [--shots <ディレクトリ>] [--fill <JSON>] [--submit <セレクター>] [--width 1280] [--user admin] [--sql <SQL>]
//   例（use_captcha=1 の状態を書き出す。DB は戻らないので、呼び出し側で控えた SQL ダンプから戻す）:
//     ... --path 'index.php?a=17' --sql "REPLACE INTO modx_system_settings (setting_name, setting_value) VALUES ('use_captcha','1')" --out <JSON>
//
// 引数:
//   --path    manager からの相対 URL（必須）
//   --out     書き出す JSON のパス
//   --shots   最初の .tab-pane のタブごとの画面写真（tab<番号>.png）の置き場所。--fill より前（開いた直後）に撮る
//   --fill    {"名前": "値"} の JSON ファイル、または JSON 文字列。DOM へ直接入れて input と change を起こす
//             （隠れたタブや行の欄にも入る）。ラジオは同じ名前で値が合うものを選ぶ。チェックボックスは値が空でなければ入れ、
//             空なら外す（value 属性は書き換えない）。文字の欄・select・hidden・textarea は同じ名前の欄が複数あっても最初の 1 つだけに入れる
//   --submit  --fill の後に押すセレクター。確認ダイアログは受け入れ、<dialog> の警告は閉じ、遷移が落ち着くのを待つ。
//             --out は着いたページを書き出す。30 秒で遷移しなければ FAIL（終了コード 1）
//   --width   ビューポートの幅（既定 1280）
//   --user    ログイン名（既定は管理者）。パスワードは管理者と同じ
//   --sql     ログインの後、ページを開く前に DB へ流す SQL（キャッシュも消す）。ログイン画面に影響する設定
//             （use_captcha=1 など）を変えた状態で書き出すときに使う。DB は元に戻さない（戻すのは呼び出し側）
//
// 出力: {"url","tabs","tabRow","fields"}。fields は form の中の名前のある欄を DOM の順に 1 件 1 行で並べる。
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { config, login, clickAlertOk, sql, appExec } = require('./lib');

// 毎回変わる値は伏せる（保存後の画面に出る sessToken も含む）
const MASK_NAMES = ['csrf_token', 'sessToken'];

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

// ページの中で動かす: 対象のフォームを選ぶ（.tab-pane を持つもの、無ければ #mainPane の中、無ければ最初）
function pickForm() {
    const forms = Array.from(document.forms);
    return forms.find(f => f.querySelector('.tab-pane')) || forms.find(f => f.closest('#mainPane')) || forms[0];
}

// ページの中で動かす: 欄の一覧を取る
function collect(maskNames) {
    const form = window.__pickForm();
    if (!form) {
        return { url: location.href, tabs: [], tabRow: [], fields: [], error: 'form が無い' };
    }
    const pane = form.querySelector('.tab-pane');
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
        // 描画の状態（閉じたタブか）に頼らず、最初の <br> かブロック要素の手前までの文字を取る
        let rowText = '';
        if (cell) {
            const blocks = /^(BR|DIV|P|TABLE|UL|OL|DL|H[1-6]|PRE|HR|FORM|TR)$/;
            const walk = n => {
                for (const c of n.childNodes) {
                    if (c.nodeType === 3) {
                        rowText += c.textContent;
                    } else if (c.nodeType === 1) {
                        if (blocks.test(c.tagName)) {
                            if (rowText.trim()) { return true; }
                            if (c.tagName === 'BR') { continue; }
                        }
                        if (walk(c)) { return true; }
                    }
                }
                return false;
            };
            walk(cell);
            rowText = rowText.replace(/\s+/g, ' ').trim();
        }
        const f = {
            tab: page && page.tabPage && page.tabPage.tab ? text(page.tabPage.tab) : '',
            row: rowText,
            name: el.name,
            tag,
            type: tag === 'select' ? (el.multiple ? 'select-multiple' : 'select-one') : (el.type || tag),
            value: maskNames.includes(el.name) ? '<masked>' : el.value,
            checked: !!el.checked,
            selected: tag === 'select' ? Array.from(el.options).map(o => [o.value, o.text]) : null,
            disabled: !!el.disabled,
            visible: !hidden(el, page || form.parentElement),
        };
        fields.push(f);
    }
    const tabRow = tabEls.map(t => {
        // スクロールに左右されないよう、タブの枠（.tab-pane）からの相対位置にする
        const r = t.getBoundingClientRect();
        const o = pane.getBoundingClientRect();
        return { text: text(t), x: Math.round(r.x - o.x), y: Math.round(r.y - o.y), w: Math.round(r.width), h: Math.round(r.height) };
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
    const form = window.__pickForm();
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
                // 管理画面の本文（#mainPane）は画面の高さの中でスクロールして、はみ出しは写らない。
                // 枠の上端に枠全体の高さを足した高さまでビューポートを広げて撮る
                const need = await page.evaluate(() => {
                    const main = document.querySelector('#mainPane');
                    if (main) { main.scrollTop = 0; }
                    window.scrollTo(0, 0);
                    const r = document.querySelector('.tab-pane').getBoundingClientRect();
                    return Math.ceil(r.top + r.height);
                });
                await page.setViewportSize({ width: opts.width, height: Math.min(Math.max(need + 40, 200), 8000) });
                await page.waitForTimeout(300);
                // 写す枠の下端が、切る側（#mainPane、無ければ画面）の下端を越えていれば、写真が途中で切れる
                const clip = await page.evaluate(() => {
                    const main = document.querySelector('#mainPane');
                    const r = document.querySelector('.tab-pane').getBoundingClientRect();
                    const limit = main ? main.getBoundingClientRect().bottom : innerHeight;
                    return { bottom: Math.round(r.bottom), limit: Math.round(limit) };
                });
                if (clip.bottom > clip.limit) {
                    console.log(`  [warn] tab${i}: 枠の下端 ${clip.bottom}px が切る側の下端 ${clip.limit}px を越えている（写真が途中で切れる）`);
                }
                await page.locator('.tab-pane').first().screenshot({ path: path.join(opts.shots, `tab${i}.png`) });
            }
            await page.setViewportSize({ width: opts.width, height: 900 });
            if (n) {
                await tabs.nth(0).click();
            }
        }

        if (opts.fill) {
            await page.evaluate(`window.__pickForm = ${pickForm.toString()}`);
            const missing = await page.evaluate(fillValues, readFill(opts.fill));
            if (missing.length) {
                console.log(`  [fill] 入れられなかった: ${missing.join(', ')}`);
            }
        }

        if (opts.submit) {
            // シェルの中のフォーム（menuForm など）は保存後も残るので、#mainPane があればその中のフォームだけに印を付ける
            await page.evaluate(() => Array.from(document.forms)
                .filter(f => !document.querySelector('#mainPane') || f.closest('#mainPane'))
                .forEach(f => { f.dataset.snapOld = '1'; }));
            await page.click(opts.submit);
            const until = Date.now() + 30000;
            let done = false;
            while (Date.now() < until) {
                if (await clickAlertOk(page)) {
                    continue;
                }
                const replaced = await page.evaluate(() => !document.querySelector('form[data-snap-old]')).catch(() => true);
                if (replaced) {
                    done = true;
                    break;
                }
                await page.waitForTimeout(300);
            }
            if (!done) {
                throw new Error('--submit の後、30 秒たっても画面が切り替わらない');
            }
            await page.waitForLoadState('networkidle').catch(() => {});
            await page.waitForTimeout(500);
        }

        await page.evaluate(`window.__pickForm = ${pickForm.toString()}`);
        const data = await page.evaluate(collect, MASK_NAMES);
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
