<?php

require_once __DIR__ . '/migration_helper.class.inc.php';

/**
 * マイグレーション（manager/includes/migrations/ の PHP ファイル）を、
 * 未適用のものだけファイル名の順に流して記録する。アップデート待ちの判定の正本（hasPending）でもある。
 *
 * マイグレーション 1 本は `return function (EvoMigrationHelper $m) { ... };` で関数を 1 つ返す。
 * 表を作るのは create_tables.sql だけで、マイグレーションは既にある表を変える。
 */
class EvoMigrator
{
    // ランナーを入れる前の DB を今の形にそろえる最初のマイグレーション
    const BASELINE_ID = '00000000_baseline';
    // 時刻の無い ID は baseline だけ
    const FILE_PATTERN = '/\A(' . self::BASELINE_ID . '|\d{8}_\d{6}_[a-z0-9_]+)\.php\z/';
    // 記録の表の id 列の長さ
    const MAX_ID_LENGTH = 100;
    const LOCK_WAIT = 30;

    public function dir(): string
    {
        return MODX_CORE_PATH . 'migrations/';
    }

    public function table(): string
    {
        return db()->getFullTableName('migrations');
    }

    /**
     * ロックの名前（GET_LOCK() の上限 64 文字に収まる 47 文字）
     */
    public function lockName(): string
    {
        return 'evo_migrations_' . md5(db()->config['dbase'] . db()->config['table_prefix']);
    }

    /**
     * ファイルの ID（名前の順）。ディレクトリが無ければ 0 本
     */
    public function all(): array
    {
        $files = glob($this->dir() . '*.php');
        if (!$files) {
            return [];
        }
        $ids = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!preg_match(self::FILE_PATTERN, $name)) {
                continue;
            }
            $id = substr($name, 0, -4);
            if (strlen($id) > self::MAX_ID_LENGTH) {
                throw new RuntimeException(
                    sprintf('Migration ID is longer than %d characters: %s', self::MAX_ID_LENGTH, $id)
                );
            }
            $ids[] = $id;
        }
        sort($ids, SORT_STRING);
        return $ids;
    }

    /**
     * 適用済みの ID => applied_at。記録の表が無ければ空
     */
    public function applied(): array
    {
        $rs = db()->query("SELECT id, applied_at FROM {$this->table()} ORDER BY id", false);
        if ($rs === false) {
            if ((int)db()->getLastErrorNo() === 1146) {
                return [];
            }
            throw new RuntimeException(
                'Failed to read migrations: ' . db()->getLastError()
            );
        }
        $applied = [];
        while ($row = db()->getRow($rs, 'assoc')) {
            $applied[$row['id']] = (int)$row['applied_at'];
        }
        db()->freeResult($rs);
        return $applied;
    }

    /**
     * 未適用の ID（名前の順）
     */
    public function pending(): array
    {
        return array_values(array_diff($this->all(), array_keys($this->applied())));
    }

    /**
     * アップデート待ちの唯一の判定。例外を投げない
     * （記録を読めないときは、確かめられないので待ちあり扱いにする）
     */
    public function hasPending(): bool
    {
        try {
            return (bool)$this->pending();
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * アップデーター（install/）を通す必要があるか。baseline のファイルがあり、まだ適用していなければ true。
     * baseline の前の DB は、イベント名の同期・utf8mb4 への変換・同梱の要素の入れ直しも要るので、
     * マイグレーションだけで上げない（記録の表が無い DB も baseline が未適用と見なす）。
     * 記録を読めないときは例外を投げる
     */
    public function requiresInstaller(): bool
    {
        if (!in_array(self::BASELINE_ID, $this->all(), true)) {
            return false;
        }
        return !isset($this->applied()[self::BASELINE_ID]);
    }

    public function ensureTable(): void
    {
        $rs = db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table()} ("
            . 'id varchar(' . self::MAX_ID_LENGTH . ') NOT NULL, '
            . "applied_at int(20) NOT NULL DEFAULT '0', "
            . 'PRIMARY KEY (id))',
            false
        );
        if ($rs === false) {
            throw new RuntimeException(
                'Failed to create migrations table: ' . db()->getLastError()
            );
        }
    }

    /**
     * 未適用を順に流し、1 本終わるごとに記録する。途中で止まっても次はその続きから
     *
     * $record を false にすると、流すだけで記録しない（アップデーターが、あとの必須の処理もすべて済んでから
     * recordApplied() でまとめて記録するため。記録しないあいだは hasPending() が true のままで、公開側は止まる）。
     * 流し直しても同じ結果になるよう、マイグレーションは冪等に書く
     *
     * @param bool $record 1 本終わるごとに記録するか
     * @return array ['applied' => [ID...], 'failed' => ID か null, 'error' => 文か null]
     */
    public function run(bool $record = true): array
    {
        $result = ['applied' => [], 'failed' => null, 'error' => null];

        $lock = db()->escape($this->lockName());
        $rs = db()->query("SELECT GET_LOCK('{$lock}', " . self::LOCK_WAIT . ')', false);
        if ($rs === false) {
            $result['error'] = 'Failed to acquire the migration lock: ' . db()->getLastError();
        } else {
            $locked = db()->getValue($rs);
            if ($locked === null) {
                $result['error'] = 'Failed to acquire the migration lock: GET_LOCK() returned NULL.';
            } elseif ((int)$locked !== 1) {
                $result['error'] = 'Could not acquire the migration lock within ' . self::LOCK_WAIT
                    . ' seconds. Another migration may be running.';
            }
        }
        if ($result['error'] !== null) {
            evo()->logEvent(0, 3, $result['error'], 'Migration', ['lock' => $this->lockName()]);
            return $result;
        }

        try {
            $this->ensureTable();
            foreach ($this->pending() as $id) {
                try {
                    $this->runOne($id, $record);
                } catch (Throwable $e) {
                    $result['failed'] = $id;
                    $result['error'] = $e->getMessage();
                    $context = $e instanceof EvoMigrationException ? $e->context() : [];
                    evo()->logEvent(0, 3, $e->getMessage(), 'Migration', array_merge($context, [
                        'migration' => $id,
                        'exception' => get_class($e),
                    ]));
                    break;
                }
                $result['applied'][] = $id;
            }
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            evo()->logEvent(0, 3, $e->getMessage(), 'Migration', ['exception' => get_class($e)]);
        } finally {
            db()->query("SELECT RELEASE_LOCK('{$lock}')", false);
        }

        // キャッシュは呼び出し側で消す（system_cache の無い古い DB では clearCache() が止まるため）
        return $result;
    }

    /**
     * 新規インストールだけで使う。すべてのマイグレーションを流さずに適用済みとして記録する
     */
    public function markAllApplied(): void
    {
        $this->ensureTable();
        $now = time();
        foreach ($this->all() as $id) {
            $this->record($id, $now, 'INSERT IGNORE');
        }
    }

    /**
     * run(false) で流したものなど、指定した ID を適用済みとして記録する。重ねて記録しても壊れない
     *
     * @param string[] $ids
     */
    public function recordApplied(array $ids): void
    {
        $this->ensureTable();
        $now = time();
        foreach ($ids as $id) {
            $this->record((string)$id, $now, 'INSERT IGNORE');
        }
    }

    private function runOne($id, bool $record = true): void
    {
        $migration = self::load($this->dir() . $id . '.php');
        if (!is_callable($migration)) {
            throw new RuntimeException("Migration {$id} must return a function.");
        }
        $migration(new EvoMigrationHelper());
        if ($record) {
            $this->record($id, time(), 'INSERT');
        }
    }

    /**
     * ファイルの変数がランナーの変数を書き換えたり、返した関数がランナーに結び付いたりしないよう、
     * 変数を持たない static な場所で読み込む
     */
    private static function load($file)
    {
        return require $file;
    }

    private function record($id, $time, $verb): void
    {
        $rs = db()->query(
            sprintf(
                "%s INTO %s (id, applied_at) VALUES ('%s', %d)",
                $verb,
                $this->table(),
                db()->escape($id),
                (int)$time
            ),
            false
        );
        if ($rs === false) {
            throw new RuntimeException(
                "Failed to record migration {$id}: " . db()->getLastError()
            );
        }
    }
}
