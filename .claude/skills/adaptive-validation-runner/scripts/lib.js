// 動作確認スクリプトの共通ヘルパー（CommonJS）。
// playwright はリポジトリに含めない。作業用ディレクトリへ入れ、NODE_PATH で解決させる:
//   NODE_PATH=<作業用>/node_modules node .claude/skills/adaptive-validation-runner/scripts/<script>.js
const { execFileSync } = require('child_process');

// 並走時は公開先のアドレスを分ける（127.0.0.2 など）。127.0.0.1 のときは従来どおり localhost で開く
const BIND_IP = process.env.EVO_BIND_IP || '127.0.0.1';
const HOST = BIND_IP === '127.0.0.1' ? 'localhost' : BIND_IP;
const HTTP_PORT = process.env.EVO_HTTP_PORT || '80';
const MAILPIT_PORT = process.env.EVO_MAILPIT_PORT || '8025';
// compose.validation.yml と同じ規則でコンテナ名を決める（並走時は EVO_VALIDATE_NAME で分ける）
const ENV_NAME = process.env.EVO_VALIDATE_NAME || 'evo-validate';

const config = {
    base: process.env.EVO_BASE_URL || `http://${HOST}${HTTP_PORT === '80' ? '' : ':' + HTTP_PORT}`,
    mailpit: process.env.EVO_MAILPIT_URL || `http://${HOST}:${MAILPIT_PORT}`,
    adminUser: process.env.EVO_ADMIN_USER || 'admin',
    adminPassword: process.env.EVO_ADMIN_PASSWORD || 'Passw0rd!2026',
    adminEmail: process.env.EVO_ADMIN_EMAIL || 'admin@example.com',
    appContainer: process.env.EVO_APP_CONTAINER || `${ENV_NAME}-app`,
    dbContainer: process.env.EVO_DB_CONTAINER || `${ENV_NAME}-db`,
    tablePrefix: process.env.EVO_TABLE_PREFIX || 'modx_',
};
config.manager = config.base + '/manager/';

const sleep = ms => new Promise(r => setTimeout(r, ms));

// DB へ SQL を流す（-N: 列名なし）。結果はタブ区切りの文字列。
// 失敗時は例外になり、mariadb のエラー出力は例外の stderr に入る（画面には出さない）
function sql(query) {
    return execFileSync('docker', [
        'exec', config.dbContainer,
        'mariadb', '-uuser', '-ppassword', 'evodb', '-N', '-e', query,
    ], { stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim();
}

// app コンテナでコマンドを実行する（作業ディレクトリは /var/www/html）
function appExec(args) {
    return execFileSync('docker', ['exec', '-w', '/var/www/html', config.appContainer, ...args]).toString().trim();
}

// 管理画面は Accept-Language が無いと 404 を返すため、必ず付ける
async function newPage(browser) {
    const ctx = await browser.newContext({ locale: 'ja-JP', extraHTTPHeaders: { 'Accept-Language': 'ja' } });
    const page = await ctx.newPage();
    page.on('pageerror', e => console.log(`  [pageerror] ${e.message}`));
    return page;
}

async function login(browser, user = config.adminUser, password = config.adminPassword) {
    const page = await newPage(browser);
    await page.goto(config.manager);
    await page.fill('#username', user);
    await page.fill('#password', password);
    await page.click('#submitButton');
    // 1.2 系の管理画面はフレームセット（frame[name="main"]）。main 以降はシェルの #mainPane
    await page.waitForSelector('#mainPane, frame[name="main"]', { state: 'attached', timeout: 15000 });
    await page.waitForLoadState('networkidle').catch(() => {});
    return page;
}

// キャッシュは「サイトをリフレッシュ」(a=26) で作り直す（CLI なら php evo cache:clear でもよい。どちらもすぐ作り直す）。
// temp/cache/*.php を直接消しても次のアクセスで作り直されるが、確認の手順ではこちらを使う
// a=26 は状態を変える操作のため、POST でしか受け付けず（GET は 405）、CSRF トークンが要る。
// トークンは <meta name="csrf-token"> から読み、開いたばかりのページなどで無ければ、先に管理画面を開いて取得する
async function refreshCache(page) {
    const readToken = () => page.evaluate(() => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }).catch(() => null);
    let token = await readToken();
    if (!token) {
        await page.goto(config.manager, { waitUntil: 'domcontentloaded' });
        token = await readToken();
    }
    if (!token) {
        throw new Error('refreshCache: CSRF トークンを取得できない（管理画面にログインしていない可能性）');
    }
    // page.request はページと Cookie（ログインのセッション）を共有する
    const res = await page.request.post(config.manager + 'index.php?a=26', { form: { csrf_token: token } });
    if (res.status() >= 400) {
        throw new Error(`refreshCache: サイトのリフレッシュ（a=26）が失敗した（status=${res.status()}）`);
    }
    await page.waitForTimeout(1000);
}

// ブランチ切り替え直後は OPcache（revalidate_freq=2）が古いコードを返すことがある
async function waitForCodeReload(ms = 3000) {
    await sleep(ms);
}

// shell.js の fetch 応答を記録する（X-Evo-Pane の有無と本文）
function watchFetch(page, urlPattern) {
    const seen = [];
    page.on('response', async res => {
        if (res.request().resourceType() !== 'fetch' || !urlPattern.test(res.url())) {
            return;
        }
        let body = '';
        try { body = await res.text(); } catch (e) {}
        seen.push({ url: res.url(), status: res.status(), pane: res.headers()['x-evo-pane'] || '', body });
    });
    return seen;
}

// jAlert（jquery.alerts）の OK を押す。シェルではダイアログが潰れて OK が画面外に出るため DOM でクリックする
async function clickAlertOk(page) {
    const ok = await page.$('#popup_ok');
    if (!ok) {
        return false;
    }
    await ok.evaluate(b => b.click());
    await page.waitForTimeout(1000);
    await page.waitForLoadState('networkidle').catch(() => {});
    return true;
}

// シェルの構造（入れ子・ダイアログ・モーダル・ローディング表示）をまとめて取る
async function shellState(page) {
    return page.evaluate(() => ({
        url: location.href,
        mainPane: document.querySelectorAll('#mainPane').length,
        topMenu: document.querySelectorAll('#topMenu').length,
        htmlInPane: !!document.querySelector('#mainPane html, #mainPane head, #mainPane body'),
        alert: (document.querySelector('#popup_message') || {}).textContent || '',
        modalOpen: !!document.querySelector('#evoShellModalOverlay:not(.hidden)'),
        working: ((document.getElementById('workText') || {}).innerHTML || '').trim() !== '',
    }));
}

// セーフモードのログイン画面（media/style/common/login.tpl）はテーマの style.css を読み込まない
async function isSafeModeLogin(page) {
    return page.evaluate(() => !!document.querySelector('#loginfrm') && !document.querySelector('link[href*="/style.css"]'));
}

async function mailpitApi(path, opts) {
    const res = await fetch(config.mailpit + '/api/v1' + path, opts);
    return (res.headers.get('content-type') || '').includes('json') ? res.json() : res.text();
}

async function clearMail() {
    await mailpitApi('/messages', { method: 'DELETE' });
}

// 最新のメールを待って返す（msg: 解析済み、raw: 生のメール、headers: 先頭ヘッダー）
async function latestMail(timeoutMs = 10000) {
    const until = Date.now() + timeoutMs;
    while (Date.now() < until) {
        const list = await mailpitApi('/messages');
        if (list.messages && list.messages.length) {
            const id = list.messages[0].ID;
            const msg = await mailpitApi(`/message/${id}`);
            const raw = await mailpitApi(`/message/${id}/raw`);
            return { msg, raw, headers: raw.split(/\r?\n\r?\n/)[0] };
        }
        await sleep(500);
    }
    return null;
}

module.exports = {
    config, sleep, sql, appExec, newPage, login, refreshCache, waitForCodeReload,
    watchFetch, clickAlertOk, shellState, isSafeModeLogin, clearMail, latestMail,
};
