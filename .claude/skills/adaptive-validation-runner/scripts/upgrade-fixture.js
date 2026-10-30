// 古い版の DB を再現し、アップグレード後のスキーマを今の新規インストールと比べるための道具。
//   [EVO_WORK_DIR=<作業用>] node .claude/skills/adaptive-validation-runner/scripts/upgrade-fixture.js <副コマンド> ...
//
//   build <tag> [--out <file>] [--charset utf8mb3|utf8mb4]
//                               リリースタグの CREATE TABLE と最小のデータから、フィクスチャの SQL を作る
//                               （既定の出力先は $EVO_WORK_DIR/fixture-<tag>.sql。utf8mb4 は -utf8mb4 が付く）
//   load <file>                 検証用 DB の接頭辞つきの表をすべて消してから、フィクスチャを流し込む
//                               （EVO_VALIDATE_NAME か EVO_DB_CONTAINER が必須）
//   migrate                     CLI のガード（requiresInstaller）を通さずに、作業ツリーのランナーを流す
//   create-tables               install/sql/create_tables.sql を、インストーラと同じ置き換えで流す
//   snapshot <out>              列と索引の定義を TSV に書く
//   diff <ref> <target>         2 つの snapshot を比べる（違いがあれば終了コード 1）
//
// 使い方と、比べる項目・比べない項目は references/upgrade-fixtures.md を見る。
// リポジトリのルート（git のタグを読める作業ツリー）で実行する。playwright は使わないので NODE_PATH は要らない。
const fs = require('fs');
const path = require('path');
const { execFileSync, spawnSync } = require('child_process');
const { config, sql } = require('./lib');

const PREFIX = config.tablePrefix;
const DATA_FILE = path.join(__dirname, 'upgrade-fixture-data.sql');
const DB_ARGS = ['-uuser', '-ppassword', '--default-character-set=utf8mb4', 'evodb'];

function fail(message) {
    console.error('FAIL ' + message);
    process.exit(1);
}

// 'abc' のような値を SQL の文字列リテラルにする（LIKE のパターンにも使うので _ と % はここでは触らない）
function quote(value) {
    return "'" + String(value).replace(/\\/g, '\\\\').replace(/'/g, "''") + "'";
}

// --- build ---

// そのタグの CREATE TABLE を持つファイル（1.0.0J〜1.0.6J / 1.0.7J〜1.0.16J / 1.0.17J 以降）
function ddlPath(tag) {
    const m = /^(\d+)\.(\d+)\.(\d+)/.exec(tag);
    if (!m) {
        fail(`タグの形が読めない: ${tag}（例: 1.0.0J、1.0.14J.r9）`);
    }
    const [major, minor, patch] = [Number(m[1]), Number(m[2]), Number(m[3])];
    if (major === 1 && minor === 0 && patch <= 6) {
        return 'install/setup.sql';
    }
    if (major === 1 && minor === 0 && patch <= 16) {
        return 'install/sql/both_createtables.sql';
    }
    return 'install/sql/create_tables.sql';
}

function gitShow(ref, file) {
    try {
        return execFileSync('git', ['show', `${ref}:${file}`], {
            stdio: ['ignore', 'pipe', 'pipe'],
            maxBuffer: 64 * 1024 * 1024,
        }).toString();
    } catch (e) {
        fail(`git show ${ref}:${file} に失敗した（タグがあるか、リポジトリのルートで実行しているか確かめる）`);
    }
}

// CREATE TABLE の文だけを取り出す（コメントと、アップグレード用の ALTER・INSERT は捨てる）。
// 同じ表の定義が 2 回あれば、最初のものだけ使う（IF NOT EXISTS の動きと同じ）
// 文字セットは tableOption（'DEFAULT CHARSET=utf8mb3' など）。取り出せなかった CREATE TABLE があれば止める
function createTables(text, tableOption) {
    const seen = new Set();
    const out = [];
    const stripComments = s => s
        .split(/\r?\n/)
        .filter(line => !/^\s*(#|--)/.test(line))
        .join('\n');
    // 元の文の中にあるすべての CREATE TABLE の表名（読み飛ばしの検出用）
    const expected = new Set();
    const nameRe = /CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`([^`]+)`/gi;
    let nm;
    while ((nm = nameRe.exec(stripComments(text))) !== null) {
        expected.add(nm[1]);
    }
    for (const raw of text.split(/;[ \t]*\r?\n/)) {
        const stmt = stripComments(raw).trim();
        const m = /^CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`([^`]+)`/i.exec(stmt);
        if (!m || seen.has(m[1])) {
            continue;
        }
        seen.add(m[1]);
        let ddl = stmt
            .split('{PREFIX}').join(PREFIX)
            // 古い版は TYPE=MyISAM。今の MariaDB は ENGINE= だけ受け付ける
            .replace(/\bTYPE\s*=\s*(\w+)/gi, 'ENGINE=$1')
            // 古いサイトの文字セットを再現する（インストーラの utf8mb4 への変換より前の状態）
            .split('{TABLE_OPTION}').join(tableOption);
        // 1.0.16J までは文字セットを書かない（インストーラが ENGINE=MyISAM のあとに足していた）。ここで足す
        if (!/\bCHARSET\s*=/i.test(ddl)) {
            if (!/\bENGINE\s*=/i.test(ddl)) {
                fail(`ENGINE も CHARSET も無い CREATE TABLE があり、文字セットを足せない: ${m[1]}`);
            }
            ddl = ddl.replace(/\bENGINE\s*=\s*(\w+)/i, `ENGINE=$1 ${tableOption}`);
        }
        out.push(ddl);
    }
    const missing = [...expected].filter(t => !seen.has(t));
    if (missing.length) {
        fail(`CREATE TABLE を取り出せなかった表がある（文の区切りの読み違いなど）: ${missing.join(', ')}`);
    }
    return out;
}

// 文字セット。utf8mb3 は古いサイトのまま、utf8mb4 は installer の convert2utf8mb4 で変換済みのサイト
// （照合順序は install/functions.php の convert2utf8mb4() と同じ utf8mb4_general_ci）
const CHARSET_OPTIONS = {
    utf8mb3: 'DEFAULT CHARSET=utf8mb3',
    utf8mb4: 'DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci',
};

// args から `--name value` を取り除いて値を返す（無ければ null）
function takeOption(args, name) {
    const i = args.indexOf(name);
    if (i === -1) {
        return null;
    }
    const value = args[i + 1];
    args.splice(i, 2);
    return value === undefined ? '' : value;
}

function cmdBuild(args) {
    let out = takeOption(args, '--out');
    const charsetOpt = takeOption(args, '--charset');
    const tag = args[0];
    const usage = '使い方: build <tag> [--out <file>] [--charset utf8mb3|utf8mb4]（例: build 1.0.0J）';
    // 値が無い・空の指定は、黙って既定に戻さず止める
    if (!tag || out === '' || charsetOpt === '') {
        fail(usage);
    }
    const charset = charsetOpt === null ? 'utf8mb3' : charsetOpt;
    if (!Object.prototype.hasOwnProperty.call(CHARSET_OPTIONS, charset)) {
        fail(`--charset は utf8mb3 か utf8mb4 だけ: ${charset}\n${usage}`);
    }
    if (!out) {
        if (!process.env.EVO_WORK_DIR) {
            fail('出力先を決められない。EVO_WORK_DIR（作業用ディレクトリ）か --out <file> を指定する');
        }
        const suffix = charset === 'utf8mb3' ? '' : `-${charset}`;
        out = path.join(process.env.EVO_WORK_DIR, `fixture-${tag}${suffix}.sql`);
    }

    const ref = `release-${tag}`;
    const file = ddlPath(tag);
    let text = gitShow(ref, file);
    // 1.0.0J の setup.sql は、CREATE TABLE のあとに「upgrade-able[[」の ALTER の塊が続く（CREATE TABLE だけ使う）
    const marker = text.search(/^#\s*upgrade-able\[\[/m);
    if (marker !== -1) {
        text = text.slice(0, marker);
    }
    const tables = createTables(text, CHARSET_OPTIONS[charset]);
    if (!tables.length) {
        fail(`${ref}:${file} から CREATE TABLE を取り出せなかった`);
    }

    const data = fs.readFileSync(DATA_FILE, 'utf8')
        .split('{PREFIX}').join(PREFIX)
        .split('{SETTINGS_VERSION}').join(tag)
        .split('{ADMIN_USER}').join(config.adminUser)
        .split('{ADMIN_EMAIL}').join(config.adminEmail);

    const header = [
        `-- ${ref}:${file} の CREATE TABLE (${tables.length} 表) と ${path.basename(DATA_FILE)} から作ったフィクスチャ`,
        '-- upgrade-fixture.js build で作る。手で編集しない',
        'SET NAMES utf8mb4;',
        "SET SESSION sql_mode = '';",
        '',
    ].join('\n');
    fs.mkdirSync(path.dirname(out), { recursive: true });
    fs.writeFileSync(out, header + tables.join(';\n\n') + ';\n\n' + data);
    console.log(`build: ${tag} -> ${out}（${tables.length} 表、定義は ${file}、文字セット ${charset}）`);
}

// --- DB を流す補助 ---

// SQL のファイル（または文字列）を DB へ流す。失敗したら標準エラーを出して終了する
function runSql(input, label) {
    const res = spawnSync('docker', ['exec', '-i', config.dbContainer, 'mariadb', ...DB_ARGS], {
        input,
        maxBuffer: 64 * 1024 * 1024,
    });
    if (res.status !== 0) {
        process.stderr.write(res.stderr);
        fail(`${label} に失敗した（mariadb の終了コード ${res.status}）`);
    }
}

function prefixedTables() {
    const like = PREFIX.replace(/[_%\\]/g, '\\$&') + '%';
    const out = sql(
        `SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ${quote(like)}`
    );
    return out ? out.split('\n') : [];
}

// --- load ---

function cmdLoad(args) {
    const file = args[0];
    if (!file) {
        fail('使い方: load <file>');
    }
    if (!fs.existsSync(file)) {
        fail(`ファイルが無い: ${file}`);
    }
    // 既定のコンテナ名（evo-validate-db）のまま別のセッションの表を消さないよう、対象を明示させる
    if (!process.env.EVO_VALIDATE_NAME && !process.env.EVO_DB_CONTAINER) {
        fail('load は表を消す。EVO_VALIDATE_NAME（か EVO_DB_CONTAINER）で対象の環境を明示する');
    }
    const tables = prefixedTables();
    console.log(`load: 対象 ${config.dbContainer} の ${tables.length} 表（接頭辞 ${PREFIX}）を消して置き換える`);

    // 管理者のパスワードは、流し込む前の DB（setup-site.js で入れた今の版）の値を引き継ぐ。
    // 前の load が途中で失敗しても使えるよう、読めた値は $EVO_WORK_DIR/admin-password.hex に控える
    const hexFile = process.env.EVO_WORK_DIR ? path.join(process.env.EVO_WORK_DIR, 'admin-password.hex') : null;
    let hex = '';
    try {
        hex = sql(`SELECT HEX(password) FROM ${PREFIX}manager_users WHERE id=1`);
    } catch (e) {
        hex = '';
    }
    // フィクスチャのパスワードは 'x'（1 文字）。それが残っているのは、前の load が途中で失敗したとき
    if (hex.length >= 20) {
        if (hexFile) {
            fs.mkdirSync(path.dirname(hexFile), { recursive: true });
            fs.writeFileSync(hexFile, hex + '\n');
        }
    } else if (hexFile && fs.existsSync(hexFile)) {
        hex = fs.readFileSync(hexFile, 'utf8').trim();
        console.log(`load: 管理者のパスワードは DB から読めないので、${hexFile} の控えを使う`);
    } else {
        fail(
            hex
                ? '管理者のパスワードがフィクスチャの仮の値のまま（前の load が途中で失敗した）で、控え（EVO_WORK_DIR の admin-password.hex）も無い。setup-site.js --reinstall で今の版を入れ直す'
                : `${PREFIX}manager_users の ID 1 が読めない。先に setup-site.js で今の版を入れる`
        );
    }
    if (!/^[0-9A-Fa-f]{20,}$/.test(hex)) {
        fail('管理者のパスワードの控えの形が正しくない。setup-site.js --reinstall で今の版を入れ直し、控えを消す');
    }

    if (tables.length) {
        runSql(
            'SET FOREIGN_KEY_CHECKS=0;\n'
            + tables.map(t => `DROP TABLE IF EXISTS \`${t}\`;`).join('\n') + '\n',
            '表の削除'
        );
    }
    runSql(fs.readFileSync(file), `${file} の流し込み`);
    const updated = sql(
        `UPDATE ${PREFIX}manager_users SET password=CONVERT(UNHEX('${hex}') USING utf8mb4) WHERE id=1; `
        + `SELECT COUNT(*) FROM ${PREFIX}manager_users WHERE id=1 AND HEX(password)='${hex}'`
    );
    if (updated !== '1') {
        fail('管理者（ID 1）のパスワードを置き換えられなかった。フィクスチャに ID 1 の管理者があるか確かめる');
    }
    console.log(`load: ${path.basename(file)}（${tables.length} 表を消して流し込み、管理者のパスワードは引き継いだ）`);
}

// --- migrate ---

// CLI の起動処理（manager/includes/cli/bootstrap.php）を読み込み、ガードを通さずにランナーを流す。
// キャッシュは消さない（system_cache の無い古い DB では clearCache() が処理ごと止めるため）。
// 出力と終了コードは php evo migrate と同じ（Applied <ID> / Nothing to migrate. / 失敗は終了コード 1）
const MIGRATE_PHP = `
define('EVO_CLI', true);
define('MODX_API_MODE', true);
require 'manager/includes/cli/bootstrap.php';
require_once MODX_CORE_PATH . 'migrator.class.inc.php';
$result = (new EvoMigrator())->run();
foreach ($result['applied'] as $id) {
    echo "Applied {$id}\\n";
}
if ($result['error'] !== null) {
    $target = $result['failed'] !== null ? "Migration {$result['failed']} failed" : 'Migration failed';
    fwrite(STDERR, "{$target}: {$result['error']}\\n");
    exit(1);
}
if (!$result['applied']) {
    echo "Nothing to migrate.\\n";
}
`;

function cmdMigrate() {
    const res = spawnSync(
        'docker',
        ['exec', '-w', '/var/www/html', config.appContainer, 'php', '-r', MIGRATE_PHP],
        { maxBuffer: 64 * 1024 * 1024 }
    );
    if (res.error) {
        fail(`docker を起動できない: ${res.error.message}`);
    }
    process.stdout.write(res.stdout);
    process.stderr.write(res.stderr);
    process.exit(res.status === null ? 1 : res.status);
}

// --- create-tables ---

function cmdCreateTables() {
    const file = 'install/sql/create_tables.sql';
    if (!fs.existsSync(file)) {
        fail(`${file} が無い。リポジトリのルートで実行する`);
    }
    // インストーラ（install/sqlParser.class.php）と同じ置き換え。文字セットは utf8mb4 / utf8mb4_general_ci
    const text = fs.readFileSync(file, 'utf8')
        .split('{TABLE_OPTION}').join('CHARSET=utf8mb4 COLLATE utf8mb4_general_ci')
        .split('{PREFIX}').join(PREFIX);
    runSql(text, 'create_tables.sql の実行');
    console.log('create-tables: install/sql/create_tables.sql を流した');
}

// --- snapshot ---

// 比べる項目: 列の型・NULL 可否・既定値・EXTRA、索引の名前・一意かどうか・種類（FULLTEXT か、そうでないか）・列の並び（前置き長を含む）。
// 種類を FULLTEXT かどうかだけにするのは、MariaDB が長い UNIQUE を HASH と報告するなど、サーバーの事情で変わるため。
// 比べない項目（列のコメント・列の順・表のコメント・文字セットと照合順序・エンジン）は references/upgrade-fixtures.md
function cmdSnapshot(args) {
    const out = args[0];
    if (!out) {
        fail('使い方: snapshot <out>');
    }
    const like = quote(PREFIX.replace(/[_%\\]/g, '\\$&') + '%');
    const strip = `SUBSTRING(TABLE_NAME, ${PREFIX.length + 1})`;
    const columns = sql(
        `SELECT 'column', ${strip}, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, `
        // 空の値は、行末だと sql() の trim で消えて項目が減るので、空にならない表記にする
        + "IF(COLUMN_DEFAULT IS NULL, '<none>', IF(CHAR_LENGTH(COLUMN_DEFAULT) = 0, '<empty>', COLUMN_DEFAULT)), IF(EXTRA = '', '-', EXTRA) "
        + `FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ${like} `
        + 'ORDER BY TABLE_NAME, COLUMN_NAME'
    );
    const indexes = sql(
        `SELECT 'index', ${strip}, INDEX_NAME, IF(NON_UNIQUE=0, 'unique', 'nonunique'), IF(INDEX_TYPE='FULLTEXT', 'FULLTEXT', 'BTREE'), `
        + "GROUP_CONCAT(CONCAT(COLUMN_NAME, IF(SUB_PART IS NULL, '', CONCAT('(', SUB_PART, ')'))) ORDER BY SEQ_IN_INDEX SEPARATOR ',') "
        + `FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ${like} `
        + 'GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE, INDEX_TYPE ORDER BY TABLE_NAME, INDEX_NAME'
    );
    if (!columns) {
        fail(`接頭辞 ${PREFIX} の表が無い`);
    }
    fs.mkdirSync(path.dirname(path.resolve(out)), { recursive: true });
    fs.writeFileSync(out, columns + '\n' + indexes + '\n');
    console.log(`snapshot: ${out}（列 ${columns.split('\n').length} 行、索引 ${indexes ? indexes.split('\n').length : 0} 行）`);
}

// --- diff ---

// TSV を { 'column\t表\t名前': '値...', ... } にする。表ごとの一覧も返す
function readSnapshot(file) {
    if (!fs.existsSync(file)) {
        fail(`ファイルが無い: ${file}`);
    }
    const items = new Map();
    const tables = new Set();
    for (const line of fs.readFileSync(file, 'utf8').split('\n')) {
        if (!line) {
            continue;
        }
        const f = line.split('\t');
        tables.add(f[1]);
        items.set(`${f[0]}\t${f[1]}\t${f[2]}`, f.slice(3).join(' | '));
    }
    return { items, tables };
}

function label(key) {
    const [kind, table, name] = key.split('\t');
    return kind === 'column' ? `${table}.${name}` : `${table} 索引 ${name}`;
}

function cmdDiff(args) {
    const [refFile, targetFile] = args;
    if (!refFile || !targetFile) {
        fail('使い方: diff <ref> <target>');
    }
    const ref = readSnapshot(refFile);
    const target = readSnapshot(targetFile);
    const diffs = [];

    // 表の単位で「ref にあって target に無い」は表ごと 1 行にまとめる
    const missingTables = [...ref.tables].filter(t => !target.tables.has(t)).sort();
    const keepTables = [...target.tables].filter(t => !ref.tables.has(t)).sort();
    for (const t of missingTables) {
        diffs.push(`表なし  ${t}（ref にあり、target に無い）`);
    }
    for (const [key, value] of ref.items) {
        const table = key.split('\t')[1];
        if (missingTables.includes(table)) {
            continue;
        }
        if (!target.items.has(key)) {
            diffs.push(`なし    ${label(key)}（ref: ${value}）`);
        } else if (target.items.get(key) !== value) {
            diffs.push(`相違    ${label(key)}\n          ref   : ${value}\n          target: ${target.items.get(key)}`);
        }
    }
    for (const [key, value] of target.items) {
        const table = key.split('\t')[1];
        if (keepTables.includes(table)) {
            continue;
        }
        if (!ref.items.has(key)) {
            diffs.push(`余分    ${label(key)}（target: ${value}）`);
        }
    }
    diffs.sort();

    console.log(`違い: ${diffs.length} 件`);
    for (const d of diffs) {
        console.log('  ' + d);
    }
    // ref に無い表は「残す表」（消えた表・サンプルの表など。利用者のデータがあるので消さない）。違いには数えない
    console.log(`残す表（ref に無い表）: ${keepTables.length} 件`);
    for (const t of keepTables) {
        console.log('  ' + t);
    }
    process.exit(diffs.length ? 1 : 0);
}

// --- main ---

const [command, ...rest] = process.argv.slice(2);
const commands = {
    build: cmdBuild,
    load: cmdLoad,
    migrate: cmdMigrate,
    'create-tables': cmdCreateTables,
    snapshot: cmdSnapshot,
    diff: cmdDiff,
};
if (!commands[command]) {
    fail('使い方: upgrade-fixture.js <build|load|migrate|create-tables|snapshot|diff> ...（冒頭のコメントを見る）');
}
commands[command](rest);
