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
        if (!$ok || filesize($tmp) === 0 || !rename($tmp, $path)) {
            unlink($tmp);
            return false;
        }
        return true;
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
