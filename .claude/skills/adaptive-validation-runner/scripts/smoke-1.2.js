// 1.2 系（フレームセットの管理画面）向けのスモークテスト。smoke.js（main のシェル前提）の代わりに使う
//   NODE_PATH=<作業用>/node_modules node <scripts>/smoke-1.2.js [label]
// 失敗が 1 件でもあれば終了コード 1 を返す
const { chromium } = require('playwright');
const { config, newPage, login, clearMail, latestMail, isSafeModeLogin } = require('./lib');

const LABEL = process.argv[2] || 'run';
const results = [];
const check = (n, ok, d = '') => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'} [${LABEL}] ${n}${d ? ' -- ' + d : ''}`); };
const bad = t => /Fatal error|Parse error|SQL syntax|Execution of a query to the database failed/i.test(t);

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const front = await fetch(config.base + '/', { headers: { 'Accept-Language': 'ja' } });
        check('表側のトップが 200', front.status === 200, `status=${front.status}`);

        const page = await login(browser);
        const frames = await page.evaluate(() => [...document.querySelectorAll('frame')].map(f => f.name).sort().join(','));
        check('管理画面: ログイン後にフレームセット（main/mainMenu/tree）', /main/.test(frames) && /mainMenu/.test(frames) && /tree/.test(frames), frames);

        for (const [name, url, sel] of [
            ['リソース編集(a=27&id=1)', 'index.php?a=27&id=1', '#mutate, form[name="mutate"]'],
            ['システム設定(a=17)', 'index.php?a=17', '#Button1'],
            ['エレメント管理(a=76)', 'index.php?a=76', 'body'],
            ['チャンク新規(a=77)', 'index.php?a=77', 'input[name="name"]'],
            ['ユーザー一覧(a=75)', 'index.php?a=75', 'body'],
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
            const ct = (mail.headers.match(/^Content-Type:\s*(.*)$/mi) || [])[1] || '';
            check('メール: UTF-8 で件名と本文が読める', /utf-8/i.test(ct) && /パスワード/.test(mail.msg.Subject) && /パスワード/.test(mail.msg.Text || ''), ct);
            const m = (mail.msg.Text || '').match(/https?:\/\/\S*fmpkey=[A-Za-z0-9]+/);
            if (m) {
                const lp = await newPage(browser);
                const dialogs = [];
                lp.on('dialog', d => { dialogs.push(d.message()); d.accept(); });
                await lp.goto(m[0].replace(/^https?:\/\/[^/]+/, config.base));
                await lp.waitForLoadState('networkidle').catch(() => {});
                check('再設定リンク: パスワード変更画面(a=28)へ進む', /a=28/.test(lp.url()) && dialogs.length === 0 && !(await isSafeModeLogin(lp)), `url=${lp.url()}`);
                await lp.context().close();
            } else {
                check('再設定リンク: 本文から取り出せる', false);
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
