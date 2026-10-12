// 検証環境のサイトをヘッドレスで新規インストールし、すぐ確認に使える状態にする。
//   NODE_PATH=<作業用>/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js [--sample] [--editor] [--reinstall]
//
// 1. install/install-config.php を一時的に置き、インストーラーを最後まで進める（--sample でサンプルサイトも入れる）
// 2. install-config.php を消す
// 3. 管理者でログインし、システム設定(a=17)を一度保存する。保存するまで settings_version が 0 のままで、
//    管理画面がシステム設定へ誘導される（#484 より前の版では、表側もメンテナンス画面(503)になる）
// 4. --editor で、権限の弱いユーザー editor（ロール3 投稿担当者、パスワードは管理者と同じ）を作る
//
// インストール済みかどうかは DB のテーブルで判定する。manager/includes/config.inc.php が残っていて DB が空のとき
// （down -v で DB だけ消した場合など）は止める。--reinstall を付けると config.inc.php を退避して入れ直す。
// リポジトリのルートで実行すること（config.inc.php の確認と sample.htaccess の復元のため）
const fs = require('fs');
const { chromium } = require('playwright');
const { config, sleep, sql, appExec, newPage, login, refreshCache } = require('./lib');

const withSample = process.argv.includes('--sample');
const withEditor = process.argv.includes('--editor');
const reinstall = process.argv.includes('--reinstall');
const CONFIG_INC = 'manager/includes/config.inc.php';

// DB コンテナは起動直後に接続を受け付けないことがあるため、つながるまで待つ
async function waitForDb(timeoutMs = 60000) {
    const until = Date.now() + timeoutMs;
    for (;;) {
        try {
            sql('select 1');
            return;
        } catch (e) {
            if (Date.now() > until) {
                throw new Error(`DB に接続できない（${config.dbContainer} が起動しているか確かめる）`);
            }
            await sleep(2000);
        }
    }
}

function hasTables() {
    return sql(`show tables like '${config.tablePrefix}system_settings'`) !== '';
}

// 戻り値: true ならインストール済み
function checkInstallState() {
    if (hasTables()) {
        return true;
    }
    if (!fs.existsSync(CONFIG_INC)) {
        return false;
    }
    if (!reinstall) {
        throw new Error(`${CONFIG_INC} があるのに DB が空です。検証用に作られたものなら --reinstall を付けて実行してください（退避してから入れ直します）`);
    }
    const backup = `${CONFIG_INC}.bak-${Date.now()}`;
    fs.renameSync(CONFIG_INC, backup);
    console.log(`${CONFIG_INC}: ${backup} へ退避した`);
    return false;
}

async function runInstaller(browser) {
    appExec(['cp', 'install/install-config.sample.php', 'install/install-config.php']);
    try {
        const page = await newPage(browser);
        page.on('dialog', d => { console.log('  [dialog]', d.message()); d.accept(); });

        // 言語選択
        await page.goto(config.base + '/install/index.php?action=mode');
        await Promise.all([page.waitForNavigation(), page.click('a.next')]);

        // DB 接続。照合順序の選択肢は「接続テスト」を押したときに読み込まれる
        await page.fill('#database_server', 'db');
        await page.fill('#database_user', 'user');
        await page.fill('#database_password', 'password');
        await page.click('#servertest');
        await page.waitForTimeout(2000);
        await page.fill('#dbase', 'evodb');
        await page.fill('#table_prefix', config.tablePrefix);
        if (await page.$('#databasetest')) {
            await page.click('#databasetest');
            await page.waitForTimeout(2000);
        }
        await page.fill('#adminname', config.adminUser);
        await page.fill('#adminemail', config.adminEmail);
        await page.fill('#adminpass', config.adminPassword);
        await page.fill('#adminpassconfirm', config.adminPassword);
        await Promise.all([page.waitForNavigation(), page.click('a.next')]);

        // インストールオプション → 最終確認（ライセンス同意が必須）→ インストール
        for (let i = 0; i < 5; i++) {
            const sample = await page.$('#installdata_field');
            if (sample && withSample && !(await sample.isChecked())) {
                await sample.check();
            }
            const agree = await page.$('#chkagree');
            if (agree && !(await agree.isChecked())) {
                await agree.check();
            }
            const next = await page.$('a.next');
            if (!next || !(await next.isVisible())) {
                break;
            }
            await Promise.all([page.waitForNavigation({ timeout: 120000 }), next.click()]);
        }
        const text = (await page.textContent('body')).replace(/\s+/g, ' ');
        if (!/インストールを実行しました|installation was successful/i.test(text)) {
            throw new Error('インストールが完了しなかった: ' + text.slice(0, 300));
        }
        console.log('installer: 完了');
        await page.context().close();
    } finally {
        appExec(['rm', '-f', 'install/install-config.php']);
    }
}

function settingsVersion() {
    return sql(`select setting_value from ${config.tablePrefix}system_settings where setting_name='settings_version'`);
}

async function saveSystemSettings(browser) {
    // システム設定を保存すると、フレンドリーURLのために sample.htaccess が .htaccess へリネームされる
    // (manager/processors/save_settings.processor.php)。作業ツリーに削除差分を残さないよう、保存前の中身で書き戻す
    // （git から戻すと、作業中の未コミットの変更が失われる）
    // 途中で失敗しても必ず書き戻す（書き戻さないまま次に実行すると、保存前の中身を控えられなくなる）
    const sampleBefore = fs.existsSync('sample.htaccess') ? fs.readFileSync('sample.htaccess') : null;

    const page = await login(browser);
    const ctx = page.context();
    try {
        page.on('dialog', d => d.accept());
        await page.goto(config.manager + 'index.php?a=17', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#Button1 a');
        // 保存ボタンはフォームを送信してページ遷移する。遷移の完了を待たずに次の画面へ進むと割り込んで失敗するため、
        // 遷移待ちとクリックを同時に待つ（クリックが失敗しても、遷移待ちの拒否が未処理のまま残らない）
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
            page.click('#Button1 a'),
        ]);
        // 保存の完了は DB で確かめる（保存されると settings_version が現在の版になる）
        const until = Date.now() + 30000;
        let version = settingsVersion();
        while ((!version || version === '0') && Date.now() < until) {
            await sleep(500);
            version = settingsVersion();
        }
        console.log('settings_version:', version);
        if (!version || version === '0') {
            throw new Error('システム設定の保存後も settings_version が更新されていない');
        }
        // 保存後のページはさらに管理画面のトップへ自動で遷移し直すため、同じページで次へ進むと割り込まれる。
        // 設定画面のページを閉じ、同じログイン状態（コンテキスト）の新しいページでキャッシュを作り直す
        await page.close();
        await refreshCache(await ctx.newPage());
    } finally {
        // 書き戻しを先に行う（コンテキストを閉じる処理が失敗しても、ファイルは必ず戻す）
        if (sampleBefore !== null && !fs.existsSync('sample.htaccess')) {
            fs.writeFileSync('sample.htaccess', sampleBefore);
            console.log('sample.htaccess: 保存前の中身で書き戻した（.htaccess は残す）');
        }
        await ctx.close();
    }
}

function createEditor() {
    const p = config.tablePrefix;
    if (sql(`select count(*) from ${p}manager_users where username='editor'`) !== '0') {
        console.log('editor: 作成済み');
        return;
    }
    const pass = config.adminPassword.replace(/'/g, "''");
    sql(`insert into ${p}manager_users (username,password) values ('editor', md5('${pass}')); `
        + `insert into ${p}user_attributes (internalKey,fullname,role,email) values (LAST_INSERT_ID(),'Editor',3,'editor@example.com');`);
    console.log('editor: 作成した（ロール3 投稿担当者）');
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        await waitForDb();
        if (checkInstallState()) {
            console.log('installer: インストール済みのため省略');
        } else {
            await runInstaller(browser);
        }
        await saveSystemSettings(browser);
        if (withEditor) {
            createEditor();
        }
        const front = await fetch(config.base + '/', { headers: { 'Accept-Language': 'ja' } });
        console.log('front:', front.status);
        console.log(`\n管理画面: ${config.manager}  ${config.adminUser} / ${config.adminPassword}`);
        console.log(`mailpit: ${config.mailpit}/`);
    } finally {
        await browser.close();
    }
})().catch(e => {
    console.error('FAIL', e.message);
    process.exit(1);
});
