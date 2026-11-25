<?php

require_once __DIR__ . '/db_tables.class.inc.php';

/**
 * ダンプ（表の構造と中身を SQL の文として並べたテキスト）を書く・読む。
 *
 * 書く側と読む側は、次の約束を共有する。
 * - 値には生の改行を入れない（改行・CR は db()->escape() で \n・\r になる）。
 * - 1 つの INSERT は複数の行のデータを持ち、データ 1 行ごとに改行する。途中の行は「),」、最後の行だけ「);」で終わる。
 * - 文は「;」で行を終える。読む側は「;」の後に空白とタブだけを挟んで改行が来たところで文を分ける。
 * mysqldump の出力（--skip-triggers。DELIMITER を含まないもの）も、この約束で読める。
 */
final class EvoSqlDump
{
    private const LF = "\n";
    private const FLUSH_BYTES = 1048576;
    // 1 つの INSERT の大きさの上限。mysqldump の既定の net_buffer_length（1MB − 2KB）と同じ値にし、
    // max_allowed_packet が 1MB の DB へ戻しても入るようにする。書く DB の max_allowed_packet が小さければそちらに合わせる
    private const STATEMENT_BYTES = 1046528;
    private const PACKET_MARGIN = 1024;

    /**
     * $tables（接頭辞の付いた表の名前）のダンプを $stream に書く。このサイトの表でない名前は飛ばす。
     * $options:
     * - drop: 表ごとに DROP TABLE IF EXISTS を書く
     * - structureOnly: 構造だけを書く表の名前の一覧。DROP もデータも書かず、CREATE TABLE IF NOT EXISTS にする
     *   （スナップショットのログの表。戻した先の履歴を消さない）
     */
    public static function write($stream, array $tables, array $options = []): bool
    {
        $dbase = (string) db()->dbase;
        if ($dbase === '' || !$tables) {
            return false;
        }
        $wanted = array_flip($tables);
        $structureOnly = array_flip($options['structureOnly'] ?? []);
        $drop = !empty($options['drop']);
        $lf = self::LF;

        $host = db()->config['host'] === '127.0.0.1' ? 'localhost' : db()->config['host'];
        $header = [
            '-- ',
            '--  MODX Version:' . evo()->getVersionData('version'),
            '--  ',
            '--  Host: ' . $host,
            '--  Generation Time: ' . evo()->toDateFormat(time()),
            '--  Server version: ' . db()->getVersion(),
            '--  PHP Version: ' . phpversion(),
            '--  Database : `' . $dbase . '`',
            '-- ',
        ];
        $ok = self::put($stream, implode($lf, $header));
        $limit = self::statementLimit();

        foreach (EvoDbTables::names() as $table) {
            if (!$ok) {
                break;
            }
            if (!isset($wanted[$table])) {
                continue;
            }
            $create = self::createStatement($table);
            if ($create === '') {
                return false;
            }

            $output = "{$lf}{$lf}-- --------------------------------------------------------{$lf}{$lf}";
            $output .= "-- {$lf}-- Table structure for table `{$table}`{$lf}";
            $output .= "-- {$lf}{$lf}";
            if (isset($structureOnly[$table])) {
                $output .= preg_replace('/\ACREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $create) . ';' . $lf . $lf;
                $ok = self::put($stream, $output);
                continue;
            }
            if ($drop) {
                $output .= 'SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;' . $lf;
                $output .= "DROP TABLE IF EXISTS `{$table}`;" . $lf;
                $output .= "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;{$lf}{$lf}";
            }
            $output .= $create . ';' . $lf . $lf;
            $output .= "-- {$lf}-- Dumping data for table `{$table}`{$lf}-- {$lf}";
            $ok = self::put($stream, $output);

            $output = '';
            $isSettings = $table === EvoDbTables::name('system_settings');
            $head = "{$lf}INSERT INTO `{$table}` VALUES{$lf}";
            $statementBytes = 0;
            $rs = db()->select('*', "`{$table}`");
            while ($ok && ($row = db()->getRow($rs))) {
                if ($isSettings) {
                    $row = self::portableSetting($row);
                }
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : "'" . db()->escape((string) $value) . "'";
                }
                $tuple = '(' . implode(',', $values) . ')';
                // 上限を超えるなら今の文を閉じて次の文にする（1 行だけで上限を超える行は、その行だけの文になる）
                if ($statementBytes > 0 && $limit < $statementBytes + strlen($tuple) + 2) {
                    $output .= ';' . $lf;
                    $statementBytes = 0;
                }
                if ($statementBytes === 0) {
                    $output .= $head . $tuple;
                    $statementBytes = strlen($head) + strlen($tuple);
                } else {
                    $output .= ',' . $lf . $tuple;
                    $statementBytes += strlen($tuple) + 2;
                }
                if (self::FLUSH_BYTES < strlen($output)) {
                    $ok = self::put($stream, $output);
                    $output = '';
                }
            }
            if ($statementBytes > 0) {
                $output .= ';' . $lf;
            }
            if ($ok) {
                $ok = self::put($stream, $output);
            }
        }
        return $ok;
    }

    /**
     * 同じディレクトリの一時ファイルへ write() し、書き終えてから $path へ rename する。
     * 失敗したとき・途中で止まったときは、$path にも一時ファイルにも中途半端なものを残さない。
     */
    public static function writeFile(string $path, array $tables, array $options = []): bool
    {
        $tmp = tempnam(dirname($path), 'dump');
        if ($tmp === false) {
            return false;
        }
        register_shutdown_function(function () use ($tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        });
        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            unlink($tmp);
            return false;
        }
        $ok = self::write($fp, $tables, $options);
        $ok = fclose($fp) && $ok;
        // tempnam() は 0600 で作るので、本体のほかのファイルと同じ権限にする
        if ($ok) {
            @chmod($tmp, evo()->newFilePermissions());
        }
        if (!$ok || filesize($tmp) === 0 || !rename($tmp, $path)) {
            unlink($tmp);
            return false;
        }
        return true;
    }

    // 表の名前の正規表現（バッククォートで囲んだ名前か、囲まない名前）
    private const NAME = '(?:`((?:[^`]|``)+)`|([0-9a-zA-Z_$]+))';

    /**
     * 文が対象にする表の名前（INSERT・REPLACE・DROP TABLE・CREATE TABLE・TRUNCATE・LOCK TABLES・ALTER TABLE）。
     * 当たらなければ ''。文は statements() が返す形（先頭のコメントを除いたもの）で渡す
     */
    public static function tableOf(string $statement): string
    {
        $m = self::matchTable($statement);
        if (!$m) {
            return '';
        }
        if (isset($m[3]) && $m[3] !== '') {
            return str_replace('``', '`', $m[3]);
        }
        return $m[4] ?? '';
    }

    /**
     * 文が指す DB の名前。USE・CREATE DATABASE・DB 名付きの表（`db`.`table`）の DB 名を返し、
     * DROP DATABASE・ALTER DATABASE（SCHEMA も同じ）は「DROP DATABASE 名前」の形で返す（DB 名と一致しないので、呼び出し側が必ず断れる）。
     * 当たらなければ ''。文は statements() が返す形で渡す
     */
    public static function databaseOf(string $statement): string
    {
        $name = self::NAME;
        $unquote = static function (array $m, int $at): string {
            return str_replace('``', '`', $m[$at] !== '' ? $m[$at] : ($m[$at + 1] ?? ''));
        };
        if (preg_match('/^USE\s+' . $name . '/i', $statement, $m)) {
            return $unquote($m, 1);
        }
        // mysqldump --databases の CREATE DATABASE /*!32312 IF NOT EXISTS*/ `db` と、/*!40000 DROP DATABASE IF EXISTS `db`*/ の形を含む
        if (preg_match(
            '/^(?:\/\*!\d+\s+)?(CREATE|DROP|ALTER)\s+(?:DATABASE|SCHEMA)\s+'
            . '(?:\/\*!\d+\s+IF\s+(?:NOT\s+)?EXISTS\s*\*\/\s*|IF\s+(?:NOT\s+)?EXISTS\s+)?' . $name . '/i',
            $statement,
            $m
        )) {
            $database = $unquote($m, 2);
            return strcasecmp($m[1], 'CREATE') === 0 ? $database : strtoupper($m[1]) . ' DATABASE ' . $database;
        }
        $m = self::matchTable($statement);
        if ($m) {
            return $unquote($m, 1);
        }
        return '';
    }

    // 表を対象にする文の、DB 名（1・2）と表名（3・4）の取り出し。当たらなければ null
    private static function matchTable(string $statement): ?array
    {
        $name = self::NAME;
        if (!preg_match(
            '/^(?:INSERT(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY))?(?:\s+IGNORE)?\s+INTO'
            . '|REPLACE(?:\s+(?:LOW_PRIORITY|DELAYED))?\s+INTO'
            . '|DROP\s+(?:TEMPORARY\s+)?TABLE(?:\s+IF\s+EXISTS)?'
            . '|CREATE\s+(?:OR\s+REPLACE\s+)?(?:TEMPORARY\s+)?TABLE(?:\s+IF\s+NOT\s+EXISTS)?'
            . '|TRUNCATE(?:\s+TABLE)?|LOCK\s+TABLES|ALTER\s+(?:IGNORE\s+)?TABLE)(?:\s+|(?=`))'
            . '(?:' . $name . '\s*\.\s*)?' . $name . '/i',
            $statement,
            $m
        )) {
            return null;
        }
        return $m;
    }

    /**
     * 戻す前の下読み。$stream のダンプを最後まで読み、対象の表の名前・指している DB の名前（どちらも重なりなし）と、
     * DELIMITER（トリガー・ストアドプロシージャー）を含むかを返す。DB には何も送らない
     */
    public static function inspect($stream): array
    {
        $tables = [];
        $databases = [];
        $delimiter = false;
        foreach (self::statements($stream) as $statement) {
            if (preg_match('/(?:\A|\n)[ \t]*DELIMITER\s/i', $statement)) {
                $delimiter = true;
            }
            $table = self::tableOf($statement);
            if ($table !== '') {
                $tables[$table] = true;
            }
            $database = self::databaseOf($statement);
            if ($database !== '') {
                $databases[$database] = true;
            }
        }
        return ['tables' => array_keys($tables), 'databases' => array_keys($databases), 'delimiter' => $delimiter];
    }

    /**
     * $stream のダンプを 1 文ずつ返す（キーは文の番号、値は先頭のコメントを除いた文）。
     * CRLF と単独の CR は LF にそろえてから分ける。番号は空の文やコメントだけの文も数える。
     */
    public static function statements($stream): Generator
    {
        $number = 0;
        $buffer = '';
        while (($chunk = fgets($stream)) !== false) {
            if (strpos($chunk, "\r") !== false) {
                $chunk = str_replace(["\r\n", "\r"], "\n", $chunk);
            }
            foreach (self::lines($chunk) as $line) {
                if (!preg_match('/;[ \t]*\n\z/', $line, $m)) {
                    $buffer .= $line;
                    continue;
                }
                $buffer .= substr($line, 0, -strlen($m[0]));
                $number++;
                $statement = self::body($buffer);
                $buffer = '';
                if ($statement !== '') {
                    yield $number => $statement;
                }
            }
        }
        $number++;
        $statement = self::body($buffer);
        if ($statement !== '') {
            yield $number => $statement;
        }
    }

    // 改行で終わる行ごとに分ける（最後の行は改行で終わらないことがある）
    private static function lines(string $text): array
    {
        $lines = [];
        $start = 0;
        while (($pos = strpos($text, "\n", $start)) !== false) {
            $lines[] = substr($text, $start, $pos - $start + 1);
            $start = $pos + 1;
        }
        if ($start < strlen($text)) {
            $lines[] = substr($text, $start);
        }
        return $lines;
    }

    // 文の先頭の行コメント（-- / #）と /* */ を除く。/*! */ は実行される文なので残す
    private static function body(string $statement): string
    {
        $statement = trim($statement);
        $prev = null;
        while ($prev !== $statement) {
            $prev = $statement;
            $statement = preg_replace('/\A\s*(?:--(?:[ \t][^\n]*|(?=\n|\z))|#[^\n]*)(?:\n|\z)/', '', $statement);
            $statement = preg_replace('/\A\s*\/\*(?!!).*?\*\//s', '', $statement);
            $statement = ltrim($statement);
        }
        return $statement;
    }

    // 1 つの INSERT の大きさの上限
    private static function statementLimit(): int
    {
        $packet = (int) db()->getValue('SELECT @@max_allowed_packet');
        if ($packet <= self::PACKET_MARGIN) {
            return self::STATEMENT_BYTES;
        }
        return min(self::STATEMENT_BYTES, $packet - self::PACKET_MARGIN);
    }

    private static function createStatement(string $table): string
    {
        $rs = db()->query("SHOW CREATE TABLE `{$table}`");
        if (!$rs) {
            return '';
        }
        $row = db()->getRow($rs, 'num');
        db()->freeResult($rs);
        return $row ? (string) $row[1] : '';
    }

    // 別の場所へ戻しても使えるよう、サイトの置き場所と URL の設定を [(base_path)] などの書き方にする
    private static function portableSetting(array $row): array
    {
        switch ($row['setting_name']) {
            case 'filemanager_path':
            case 'rb_base_dir':
                if (strpos($row['setting_value'], MODX_BASE_PATH) === 0) {
                    $row['setting_value'] = str_replace(MODX_BASE_PATH, '[(base_path)]', $row['setting_value']);
                }
                break;
            case 'site_url':
                if ($row['setting_value'] === MODX_SITE_URL) {
                    $row['setting_value'] = '[(site_url)]';
                }
                break;
            case 'base_url':
                if ($row['setting_value'] === MODX_BASE_URL) {
                    $row['setting_value'] = '[(base_url)]';
                }
                break;
        }
        return $row;
    }

    private static function put($stream, string $data): bool
    {
        return $data === '' || fwrite($stream, $data) === strlen($data);
    }
}
