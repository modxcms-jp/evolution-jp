// 検証環境の基本動作を確かめるスモークテスト。個別の確認スクリプトを書くときの見本も兼ねる。
//   NODE_PATH=<作業用>/node_modules node .claude/skills/adaptive-validation-runner/scripts/smoke.js
// 失敗が 1 件でもあれば終了コード 1 を返す
const { chromium } = require('playwright');
const {
    config, newPage, login, shellState, isSafeModeLogin, clearMail, latestMail,
} = require('./lib');

const results = [];
function check(name, ok, detail = '') {
    results.push(ok);
    console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail ? ' -- ' + detail : ''}`);
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        // 1. 表側
        const front = await fetch(config.base + '/', { headers: { 'Accept-Language': 'ja' } });
        check('表側のトップが 200', front.status === 200, `status=${front.status}`);

        // 2. 管理画面のシェルで、リソースの編集画面を断片として開く
        const page = await login(browser);
        // EvoShell.navigate() は fetch を始めてすぐ戻るため、応答と画面の更新を明示的に待つ
        const [res] = await Promise.all([
            page.waitForResponse(x => /a=27/.test(x.url()) && x.request().resourceType() === 'fetch', { timeout: 15000 }),
            page.evaluate(() => EvoShell.navigate('index.php?a=27&id=1')),
        ]);
        const body = await res.text();
        await page.waitForURL(/a=27/, { timeout: 15000 }).catch(() => {});
        await page.waitForFunction(() => !((document.getElementById('workText') || {}).innerHTML || '').trim(), null, { timeout: 15000 }).catch(() => {});
        const pane = res.headers()['x-evo-pane'] || '';
        const s = await shellState(page);
        check('シェル: 編集画面が X-Evo-Pane の断片で返る', pane === '1' && !/<\s*html\b/i.test(body), `pane=${pane}`);
        check('シェル: #mainPane と #topMenu が 1 つずつ', s.mainPane === 1 && s.topMenu === 1 && !s.htmlInPane, JSON.stringify({ mainPane: s.mainPane, topMenu: s.topMenu }));
        await page.context().close();

        // 3. パスワード変更リクエストのメール（mailpit で受け取る）
        await clearMail();
        const anon = await newPage(browser);
        await anon.goto(config.manager + 'index.php?action=send_email&email=' + encodeURIComponent(config.adminEmail));
        const mail = await latestMail();
        check('メール: mailpit に届く', !!mail);
        if (mail) {
            const ct = (mail.headers.match(/^Content-Type:\s*(.*)$/mi) || [])[1] || '';
            check('メール: UTF-8 で、件名と本文が読める', /utf-8/i.test(ct) && /パスワード/.test(mail.msg.Subject) && /パスワード/.test(mail.msg.Text || ''), ct);

            // 4. 再設定リンクをログインしていない状態で開く
            // fmpkey は URL 向けの base64 なので - と _ も含む
            const m = (mail.msg.Text || '').match(/https?:\/\/\S*fmpkey=[A-Za-z0-9_-]+/);
            if (m) {
                const linkPage = await newPage(browser);
                const dialogs = [];
                linkPage.on('dialog', d => { dialogs.push(d.message()); d.accept(); });
                await linkPage.goto(m[0].replace(/^https?:\/\/[^/]+/, config.base));
                await linkPage.waitForLoadState('networkidle').catch(() => {});
                const safe = await isSafeModeLogin(linkPage);
                check('再設定リンク: パスワード変更画面(a=28)へ進む', /a=28/.test(linkPage.url()) && dialogs.length === 0 && !safe, `url=${linkPage.url()} dialogs=${dialogs.join('/')}`);
                await linkPage.context().close();
            } else {
                check('再設定リンク: 本文から取り出せる', false);
            }
        }
        await anon.context().close();
    } finally {
        await browser.close();
    }
    const failed = results.filter(x => !x).length;
    console.log(`\n${results.length - failed}/${results.length} PASS`);
    process.exit(failed ? 1 : 0);
})().catch(e => {
    console.error('FAIL', e.message);
    process.exit(1);
});
