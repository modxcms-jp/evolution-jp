<?php

/**
 * マイグレーションの失敗。ログに残すコンテキストを持ち、ランナーが 1 回だけ記録する
 * （マイグレーション側はログせずに投げるだけでよい）
 */
class EvoMigrationException extends RuntimeException
{
    private $context;

    public function __construct($message, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    public function context(): array
    {
        return $this->context;
    }
}

/**
 * マイグレーションの関数に渡す補助。存在確認と、それに基づく変更だけを持つ（Schema Builder は作らない）。
 *
 * 表と列の名前、定義の文字列は、マイグレーションに書いた固定の文字列だけを渡す（リクエストの値は渡さない）。
 * 値は SQL を組み立てる直前に db()->escape() か (int) で安全化する。
 * 表が無いときの変更は何もしない（表を作るのは create_tables.sql だけ）。
 */
class EvoMigrationHelper
{
    /**
     * 接頭辞付きの完全な表名（バッククォート付き）
     */
    public function table($name): string
    {
        $this->assertName($name);
        return db()->getFullTableName($name);
    }

    /**
     * SQL を実行する。失敗したら EvoMigrationException を投げる。
     * 例外の文とログには SQL の先頭 200 文字までを入れる。設定の値や利用者のデータを含む SQL では、
     * $summary に値を含まない説明を渡すと、SQL の代わりにそれだけを出す
     */
    public function exec($sql, $summary = null)
    {
        $rs = db()->query($sql, false);
        if ($rs === false) {
            $shown = $summary !== null ? $summary : $this->shorten($sql, 200);
            $errno = (int)db()->getLastErrorNo();
            throw new EvoMigrationException(
                sprintf('SQL failed: %s (errno %d: %s)', $shown, $errno, db()->getLastError()),
                ['sql' => $shown, 'errno' => $errno]
            );
        }
        return $rs;
    }

    /**
     * SELECT / SHOW の結果を行の配列で返す
     */
    public function rows($sql, $summary = null): array
    {
        $rs = $this->exec($sql, $summary);
        $rows = [];
        if (db()->isResult($rs)) {
            while ($row = db()->getRow($rs, 'assoc')) {
                $rows[] = $row;
            }
            db()->freeResult($rs);
        }
        return $rows;
    }

    public function tableExists($table): bool
    {
        $this->assertName($table);
        return (bool)$this->rows(sprintf(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s'",
            db()->escape(db()->config['dbase']),
            db()->escape(db()->config['table_prefix'] . $table)
        ));
    }

    public function columnExists($table, $column): bool
    {
        return $this->column($table, $column) !== null;
    }

    /**
     * INFORMATION_SCHEMA.COLUMNS の 1 行（COLUMN_NAME・COLUMN_TYPE・IS_NULLABLE・COLUMN_DEFAULT・EXTRA）。無ければ null
     */
    public function column($table, $column): ?array
    {
        $this->assertName($table);
        $this->assertName($column);
        $rows = $this->rows(sprintf(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA'
            . " FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s' AND COLUMN_NAME='%s'",
            db()->escape(db()->config['dbase']),
            db()->escape(db()->config['table_prefix'] . $table),
            db()->escape($column)
        ));
        return $rows[0] ?? null;
    }

    /**
     * 索引の情報。索引や表が無ければ null
     * ['columns' => 列(並び順), 'unique' => bool, 'type' => 'BTREE'|'FULLTEXT'|..., 'prefix' => 前置き長のある列があるか]
     */
    public function index($table, $index): ?array
    {
        if (!$this->tableExists($table)) {
            return null;
        }
        $rows = $this->rows(sprintf(
            "SHOW INDEX FROM %s WHERE Key_name='%s'",
            $this->table($table),
            db()->escape($index)
        ));
        if (!$rows) {
            return null;
        }
        usort($rows, function ($a, $b) {
            return (int)$a['Seq_in_index'] <=> (int)$b['Seq_in_index'];
        });
        $prefix = false;
        foreach ($rows as $row) {
            if ($row['Sub_part'] !== null) {
                $prefix = true;
            }
        }
        return [
            'columns' => array_column($rows, 'Column_name'),
            'unique' => (int)$rows[0]['Non_unique'] === 0,
            'type' => strtoupper($rows[0]['Index_type']),
            'prefix' => $prefix,
        ];
    }

    /**
     * 索引の列（並び順）。索引や表が無ければ空
     */
    public function indexColumns($table, $index): array
    {
        $info = $this->index($table, $index);
        return $info ? $info['columns'] : [];
    }

    public function indexExists($table, $index): bool
    {
        return (bool)$this->indexColumns($table, $index);
    }

    public function primaryKey($table): array
    {
        return $this->indexColumns($table, 'PRIMARY');
    }

    /**
     * 列が無ければ足す。$definition は型以降（AFTER を含めてよい）。足したら true
     */
    public function addColumn($table, $column, $definition): bool
    {
        if (!$this->tableExists($table) || $this->columnExists($table, $column)) {
            return false;
        }
        $this->exec(sprintf(
            'ALTER TABLE %s ADD COLUMN `%s` %s',
            $this->table($table),
            $column,
            $definition
        ));
        return true;
    }

    /**
     * 型・NULL 可否・既定値のどれかが最終形と違うときだけ MODIFY する。違って変えたら true
     *
     * @param string      $definition MODIFY COLUMN に渡す型以降の定義（COMMENT を含めてよい）
     * @param string      $type       最終形の COLUMN_TYPE（例: 'varchar(245)'）
     * @param bool        $nullable   最終形が NULL 可か
     * @param string|null $default    最終形の既定値（NULL が既定なら null）
     */
    public function modifyColumn($table, $column, $definition, $type, $nullable, $default): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        $current = $this->column($table, $column);
        if ($current === null) {
            return false;
        }

        $currentDefault = $current['COLUMN_DEFAULT'];
        if ($currentDefault !== null) {
            // MariaDB 10.2.7 以降は文字列の既定値を引用符つきで返し、NULL は文字列の NULL で返す
            if ($currentDefault === 'NULL' && $current['IS_NULLABLE'] === 'YES') {
                $currentDefault = null;
            } elseif (preg_match("/\\A'(.*)'\\z/s", $currentDefault, $m)) {
                $currentDefault = str_replace("''", "'", $m[1]);
            }
        }

        $same = $this->normalizeType($current['COLUMN_TYPE']) === $this->normalizeType($type)
            && ($current['IS_NULLABLE'] === 'YES') === (bool)$nullable
            && $currentDefault === ($default === null ? null : (string)$default);
        if ($same) {
            return false;
        }

        $this->exec(sprintf(
            'ALTER TABLE %s MODIFY COLUMN `%s` %s',
            $this->table($table),
            $column,
            $definition
        ));
        return true;
    }

    /**
     * 主キーを $columns にそろえる。外す操作と付ける操作を 1 本の ALTER にする。
     * $dropIndexes は同じ ALTER で、あれば外す索引。変えたら true
     */
    public function syncPrimaryKey($table, array $columns, array $dropIndexes = []): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        $parts = [];
        foreach ($dropIndexes as $index) {
            if ($this->indexExists($table, $index)) {
                $parts[] = sprintf('DROP INDEX `%s`', $this->assertName($index));
            }
        }
        $current = $this->primaryKey($table);
        if ($current !== $columns) {
            if ($current) {
                $parts[] = 'DROP PRIMARY KEY';
            }
            $parts[] = 'ADD PRIMARY KEY (' . $this->columnList($columns) . ')';
        }
        if (!$parts) {
            return false;
        }
        $this->exec(sprintf('ALTER TABLE %s %s', $this->table($table), implode(', ', $parts)));
        return true;
    }

    /**
     * 列の並び・一意か・種類（FULLTEXT か）・前置き長のあるなしが同じ索引が既にあれば何もしない。
     * 違えば既存を落として作り直す（UNIQUE にする前の重複は、呼び出し側が removeDuplicates() で除く）。変えたら true
     *
     * @param string $kind '' | 'UNIQUE' | 'FULLTEXT'
     */
    public function addIndex($table, $name, array $columns, $kind = ''): bool
    {
        if (!in_array($kind, ['', 'UNIQUE', 'FULLTEXT'], true)) {
            throw new InvalidArgumentException("Unknown index kind: {$kind}");
        }
        if (!$this->tableExists($table)) {
            return false;
        }
        $current = $this->index($table, $name);
        if (
            $current
            && $current['columns'] === $columns
            && $current['unique'] === ($kind === 'UNIQUE')
            && $current['type'] === ($kind === 'FULLTEXT' ? 'FULLTEXT' : 'BTREE')
            && !$current['prefix']
        ) {
            return false;
        }
        $this->assertName($name);
        $parts = [];
        if ($current) {
            $parts[] = sprintf('DROP INDEX `%s`', $name);
        }
        $parts[] = sprintf(
            'ADD %s `%s` (%s)',
            $kind === '' ? 'INDEX' : $kind . ' INDEX',
            $name,
            $this->columnList($columns)
        );
        $this->exec(sprintf('ALTER TABLE %s %s', $this->table($table), implode(', ', $parts)));
        return true;
    }

    public function dropIndexIfExists($table, $name): bool
    {
        if (!$this->indexExists($table, $name)) {
            return false;
        }
        $this->exec(sprintf(
            'ALTER TABLE %s DROP INDEX `%s`',
            $this->table($table),
            $this->assertName($name)
        ));
        return true;
    }

    public function dropColumnIfExists($table, $column): bool
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return false;
        }
        $this->exec(sprintf(
            'ALTER TABLE %s DROP COLUMN `%s`',
            $this->table($table),
            $column
        ));
        return true;
    }

    public function dropTableIfExists($table): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        $this->exec('DROP TABLE ' . $this->table($table));
        return true;
    }

    /**
     * キーの列が同じ行を 1 行だけ残して消し、消した件数を返す（件数はログにも残す）。
     * 重複の組ごとに「残す行を決める → 組の行を消す → 残す行を入れ直す」で行い、一時の表は作らない。
     *
     * @param string $keepRule 残す行を決める ORDER BY の式（先頭の行を残す。例: 'priority ASC'）。
     *                         空なら、並べ替えずに読んだときの最後の行を残す（後の値で上書きしていく読み方に合わせる）
     */
    public function removeDuplicates($table, array $keyColumns, $keepRule = ''): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        $fullTable = $this->table($table);
        $keys = $this->columnList($keyColumns);
        $groups = $this->rows(
            "SELECT {$keys} FROM {$fullTable} GROUP BY {$keys} HAVING COUNT(*) > 1"
        );

        $removed = 0;
        foreach ($groups as $group) {
            $conditions = [];
            foreach ($keyColumns as $column) {
                $conditions[] = sprintf('`%s`<=>%s', $column, $this->literal($group[$column]));
            }
            $where = implode(' AND ', $conditions);

            $order = $keepRule === '' ? '' : ' ORDER BY ' . $keepRule;
            $rows = $this->rows(
                "SELECT * FROM {$fullTable} WHERE {$where}{$order}",
                "SELECT rows of a duplicate group in {$table}"
            );
            if (!$rows) {
                continue;
            }
            $keep = $keepRule === '' ? end($rows) : $rows[0];

            $fields = [];
            $values = [];
            foreach ($keep as $name => $value) {
                $fields[] = "`{$name}`";
                $values[] = $this->literal($value);
            }

            // InnoDB では、消したあとの入れ直しが失敗しても行を失わない（MyISAM では戻せない）
            $this->exec('START TRANSACTION');
            try {
                $this->exec("DELETE FROM {$fullTable} WHERE {$where}", "DELETE a duplicate group from {$table}");
                $this->exec(
                    sprintf(
                        'INSERT INTO %s (%s) VALUES (%s)',
                        $fullTable,
                        implode(',', $fields),
                        implode(',', $values)
                    ),
                    "INSERT the kept row into {$table}"
                );
                $this->exec('COMMIT');
            } catch (Throwable $e) {
                db()->query('ROLLBACK', false);
                throw $e;
            }
            $removed += count($rows) - 1;
        }

        if ($removed) {
            $this->log('Removed duplicate rows', [
                'table' => $table,
                'key' => $keyColumns,
                'removed' => $removed,
            ], 2);
        }
        return $removed;
    }

    /**
     * システム設定の読み書き。evo()->config や regOption() を使わず、system_settings を直接読み書きする
     * （インストーラ・CLI・管理画面のどこから呼んでも同じに動かすため）。
     * 同じ名前の行が複数あるときは、今のコードの読み方に合わせて最後の行の値を返す
     */
    public function setting($name, $default = null)
    {
        $rows = $this->rows(
            sprintf(
                "SELECT setting_value FROM %s WHERE setting_name='%s'",
                $this->table('system_settings'),
                db()->escape($name)
            ),
            "SELECT setting {$name}"
        );
        return $rows ? $rows[count($rows) - 1]['setting_value'] : $default;
    }

    public function hasSetting($name): bool
    {
        return (bool)$this->rows(sprintf(
            "SELECT 1 FROM %s WHERE setting_name='%s'",
            $this->table('system_settings'),
            db()->escape($name)
        ));
    }

    public function saveSetting($name, $value): void
    {
        if ($this->hasSetting($name)) {
            $this->exec(
                sprintf(
                    "UPDATE %s SET setting_value=%s WHERE setting_name='%s'",
                    $this->table('system_settings'),
                    $this->literal($value),
                    db()->escape($name)
                ),
                "UPDATE setting {$name}"
            );
            return;
        }
        $this->exec(
            sprintf(
                "INSERT INTO %s (setting_name, setting_value) VALUES ('%s', %s)",
                $this->table('system_settings'),
                db()->escape($name),
                $this->literal($value)
            ),
            "INSERT setting {$name}"
        );
    }

    public function deleteSetting($name): bool
    {
        if (!$this->hasSetting($name)) {
            return false;
        }
        $this->exec(sprintf(
            "DELETE FROM %s WHERE setting_name='%s'",
            $this->table('system_settings'),
            db()->escape($name)
        ));
        return true;
    }

    /**
     * @param int $type 1 = 情報、2 = 警告、3 = エラー
     */
    public function log($message, array $context = [], $type = 1): void
    {
        evo()->logEvent(0, $type, $message, 'Migration', $context);
    }

    private function assertName($name)
    {
        if (!is_string($name) || !preg_match('/\A[A-Za-z0-9_]+\z/', $name)) {
            throw new InvalidArgumentException('Invalid identifier: ' . var_export($name, true));
        }
        return $name;
    }

    /**
     * 例外の文とログに入れる SQL を切り詰める（mbstring が無い環境ではバイト数で切る）
     */
    private function shorten($text, $width): string
    {
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $width, '...');
        }
        if (strlen($text) <= $width) {
            return $text;
        }
        return substr($text, 0, $width - 3) . '...';
    }

    private function columnList(array $columns): string
    {
        $list = [];
        foreach ($columns as $column) {
            $list[] = '`' . $this->assertName($column) . '`';
        }
        return implode(', ', $list);
    }

    private function literal($value): string
    {
        return $value === null ? 'NULL' : "'" . db()->escape((string)$value) . "'";
    }

    /**
     * MySQL 8.0.19 以降は整数型の表示幅を返さないので、比べるときは幅を除く
     */
    private function normalizeType($type): string
    {
        return preg_replace('/\A(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', strtolower((string)$type));
    }
}
