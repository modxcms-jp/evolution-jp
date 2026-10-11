// 1.2 系（フレームセットの管理画面）向けのスモークテスト。smoke.js（main のシェル前提）の代わりに使う
//   NODE_PATH=<作業用>/node_modules node <scripts>/smoke-1.2.js [label]
// 合否判定の check が 1 件でも落ちれば終了コード 1。既知差（1.2 系でもともと異なる挙動）は info で参考表示し、
// 終了コードには影響させない（基準状態でも異常終了させないため）。
const { chromium } = require('playwright');
const { config, newPage, login, clearMail, latestMail, isSafeModeLogin } = require('./lib');

const LABEL = process.argv[2] || 'run';
const results = [];
const check = (n, ok, d = '') => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'} [${LABEL}] ${n}${d ? ' -- ' + d : ''}`); };
// 既知差。合否（results）には積まず、参考として表示するだけ
const info = (n, ok, d = '') => { console.log(`${ok ? 'ok ' : 'diff'} [${LABEL}] (参考) ${n}${d ? ' -- ' + d : ''}`); };
const bad = t => /Fatal error|Parse error|SQL syntax|Execution of a query to the database failed/i.test(t);

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const front = await fetch(config.base + '/', { headers: { 'Accept-Language': 'ja' } });
        check('表側のトップが 200', front.status === 200, `status=${front.status}`);

        const page = await login(browser);
        // フレーム名は完全一致で個別に確認する（部分一致だと main 欠落を mainMenu が隠す）
        const names = await page.evaluate(() => [...document.querySelectorAll('frame')].map(f => f.name));
        const hasFrames = ['main', 'mainMenu', 'tree'].every(n => names.includes(n));
        check('管理画面: ログイン後にフレームセット（main/mainMenu/tree）', hasFrames, names.join(','));

        for (const [name, url, sel] of [
            ['リソース編集(a=27&id=1)', 'index.php?a=27&id=1', '#mutate, form[name="mutate"]'],
            ['システム設定(a=17)', 'index.php?a=17', '#Button1'],
            ['エレメント管理(a=76)', 'index.php?a=76', '#elementsPane, #tabTemplates'],
            ['チャンク新規(a=77)', 'index.php?a=77', 'input[name="name"]'],
            ['ユーザー管理(a=75)', 'index.php?a=75', 'a[href*="a=11"]'],
        ]) {
            const res = await page.goto(config.manager + url, { waitUntil: 'domcontentloaded' });
            const html = await page.content();
            const has = !!(await page.$(sel));
            check(`管理画面: ${name} が 200 でエラーなし`, res.status() === 200 && has && !bad(html), `status=${res.status()} sel=${has}`);
        }
        await page.context().close();

        await clearMail();
        const anon = await newPage(browser);
        await anon.goto(config.manager + 'index.php?action=send_email&email=' + encodeURIComponent(config.adminEmail));
        const mail = await latestMail();
        check('メール: パスワード再設定メールが mailpit に届く', !!mail);
        if (mail) {
            // 本文からリンクを取り出せること（配信の健全性）は合否に含める
            const m = (mail.msg.Text || '').match(/https?:\/\/\S*fmpkey=[A-Za-z0-9]+/);
            check('メール: 本文から再設定リンクを取り出せる', !!m);

            // 以下は 1.2 系でもともと異なる既知差（1.2.1J / 1.2.2J とも同じ）。合否には含めない
            const ct = (mail.headers.match(/^Content-Type:\s*(.*)$/mi) || [])[1] || '';
            info('メールの文字コードが UTF-8（1.2 系は ISO-2022-JP のことがある）', /utf-8/i.test(ct), ct);
            if (m) {
                const lp = await newPage(browser);
                const dialogs = [];
                lp.on('dialog', d => { dialogs.push(d.message()); d.accept(); });
                await lp.goto(m[0].replace(/^https?:\/\/[^/]+/, config.base));
                await lp.waitForLoadState('networkidle').catch(() => {});
                info('再設定リンクが a=28 へ進む（1.2 系は進まないことがある）', /a=28/.test(lp.url()) && dialogs.length === 0 && !(await isSafeModeLogin(lp)), `url=${lp.url()}`);
                await lp.context().close();
            }
        }
        await anon.context().close();
    } finally {
        await browser.close();
    }
    const failed = results.filter(x => !x).length;
    console.log(`${LABEL} smoke: ${results.length - failed}/${results.length} PASS`);
    process.exit(failed ? 1 : 0);
})().catch(e => { console.error('FAIL', e.message); process.exit(1); });
