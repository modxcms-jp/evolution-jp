<?php

require_once __DIR__ . '/db_tables.class.inc.php';
require_once __DIR__ . '/sql_dump.class.inc.php';
require_once __DIR__ . '/snapshot_store.class.inc.php';

/**
 * スナップショットの作成と、ダンプの復元の手順を受け持つ。
 * 管理画面・CLI・$modx->snapshot() はこの手順を呼ぶ。
 * 保存先・名前・一覧・世代は EvoSnapshotStore（store()）、ダンプの形は EvoSqlDump が受け持つ。
 */
class EvoBackupManager
{
    private $store;
    private $pruned = [];

    public function __construct()
    {
        $this->store = new EvoSnapshotStore();
    }

    public function store()
    {
        return $this->store;
    }

    /**
     * スナップショットを作り、古い世代を消す。作ったファイルの名前を返す（作れなければ false）。
     * $name が空なら自動の名前にする。$options:
     * - contentsOnly: コンテンツの表だけを書く（管理画面の「コンテンツのみ」と $modx->snapshot() の target=c）
     * - writer: callable($path, array $tables, array $structureOnly): bool。書き手を替える（CLI の mysqldump）。
     *   $structureOnly の表（ログの表）は構造だけを書く。既定は EvoSqlDump
     * - keep: 残す自動の名前の世代の数（既定 EvoSnapshotStore::KEEP）。消した名前は pruned() で得る
     */
    public function createSnapshot($name = '', array $options = [])
    {
        $this->pruned = [];
        if ($name === '') {
            $name = $this->store->autoFilename();
        }
        if (!$this->store->isValidFilename($name)) {
            return false;
        }
        if (!$this->store->ensureDir()) {
            return false;
        }

        $this->extendTimeLimit();
        $tables = EvoDbTables::names();
        if (!empty($options['contentsOnly'])) {
            $tables = array_values(array_intersect($tables, self::contentTables()));
        }
        $path = $this->store->dir() . $name;
        // ログの表は、無ければ作るだけにし、データは入れない（戻した先の履歴を消さない）
        $structureOnly = self::logTables();
        if (isset($options['writer'])) {
            $written = (bool)call_user_func($options['writer'], $path, $tables, $structureOnly);
        } else {
            $written = EvoSqlDump::writeFile($path, $tables, ['drop' => true, 'structureOnly' => $structureOnly]);
        }
        if (!$written) {
            return false;
        }

        $this->pruned = $this->store->prune($options['keep'] ?? EvoSnapshotStore::KEEP);
        return $name;
    }

    /**
     * 直前の createSnapshot() が世代の整理で消したファイルの名前
     */
    public function pruned()
    {
        return $this->pruned;
    }

    // 「コンテンツのみ」のスナップショットに入れる表
    private static function contentTables(): array
    {
        return array_map([EvoDbTables::class, 'name'], [
            'site_content',
            'site_htmlsnippets',
            'site_templates',
            'system_settings',
            'site_tmplvars',
            'site_tmplvar_access',
            'site_tmplvar_contentvalues',
            'site_tmplvar_templates',
        ]);
    }

    // スナップショットに構造だけを入れる表。event_log は今は作らないが、古い版から上げたサイトには残っている
    private static function logTables(): array
    {
        return array_map([EvoDbTables::class, 'name'], ['event_log', 'manager_log', 'system_cache']);
    }

    /**
     * ダンプのファイルを 1 文ずつ流して読んで戻す（ファイル全体をメモリに読まない）。
     * 書き換える前に今の DB を復元の前の控えとして保存する。$preRestore は控えの書き手（callable($path): bool。
     * 既定は createSnapshot() と同じ PHP の書き手）。環境ごとの設定（site_url など）は取り込み先の値を残し、
     * 最後にキャッシュを作り直す。ファイルを開けなければ、何もせずに read_failed を true にして返す。
     * 書き換える前にファイルを下読みし、このサイトの接頭辞で始まらない表を含むダンプ（refused = 'prefix'）と、
     * DELIMITER を含むダンプ（refused = 'delimiter'）は、控えも作らず何も書き換えずに断る
     */
    public function restoreFile($path, $preRestore = null)
    {
        $result = [
            'ok'                 => false,
            'executed'           => 0,
            'skipped'            => 0,
            'error'              => '',
            'failed_at'          => 0,
            'pre_restore'        => '',
            'pre_restore_failed' => false,
            'read_failed'        => false,
            'refused'            => '',
            'dump_prefix'        => null,
            'foreign_tables'     => [],
        ];

        // 控えを作る前に開く（世代の整理が、戻すファイルそのもの（古い控えなど）を消しても読めるように）
        $stream = is_readable($path) ? fopen($path, 'rb') : false;
        if ($stream === false) {
            $result['read_failed'] = true;
            return $result;
        }

        $this->extendTimeLimit();
        $inspected = EvoSqlDump::inspect($stream);
        if ($inspected['delimiter']) {
            fclose($stream);
            $result['refused'] = 'delimiter';
            return $result;
        }
        $foreign = self::foreignTables($inspected['tables']);
        if ($foreign) {
            fclose($stream);
            $result['refused'] = 'prefix';
            $result['foreign_tables'] = $foreign;
            $result['dump_prefix'] = self::dumpPrefix($foreign);
            return $result;
        }
        if (!rewind($stream)) {
            fclose($stream);
            $result['read_failed'] = true;
            return $result;
        }

        // 失敗したときに戻せるよう、書き換える前に今の DB を保存する
        $name = $this->store->autoFilename(EvoSnapshotStore::PRE_RESTORE_PREFIX);
        if ($preRestore === null) {
            $saved = $this->createSnapshot($name) !== false;
        } else {
            $saved = $this->store->ensureDir() && call_user_func($preRestore, $this->store->dir() . $name);
        }
        if (!$saved) {
            fclose($stream);
            $result['pre_restore_failed'] = true;
            return $result;
        }
        $result['pre_restore'] = $name;
        $this->store->prune();

        $cacheTable = EvoDbTables::name('system_cache');
        if (EvoDbTables::exists($cacheTable)) {
            db()->query('TRUNCATE TABLE `' . $cacheTable . '`');
        }

        $keep = $this->getKeptSettings();
        $this->extendTimeLimit();

        foreach (EvoSqlDump::statements($stream) as $number => $statement) {
            if (EvoSqlDump::tableOf($statement) === $cacheTable) {
                $result['skipped']++;
                continue;
            }
            if (db()->query($statement, false) === false) {
                $result['error'] = db()->getLastError();
                $result['failed_at'] = $number;
                // 途中で止まった DB ではキャッシュの再生成が止まりうるので、設定だけ戻す
                if (EvoDbTables::exists(EvoDbTables::name('system_settings'))) {
                    $this->restoreSettings($keep);
                }
                fclose($stream);
                return $result;
            }
            $result['executed']++;
        }
        fclose($stream);

        if (!$this->finish($keep)) {
            $result['error'] = 'system_settings or system_cache is missing; cache was not rebuilt';
        }
        $result['ok'] = true;
        return $result;
    }

    // 本体の表の名前（接頭辞を除いたもの）。ダンプの接頭辞を見分けるのに使う
    private const CORE_TABLES = ['system_settings', 'site_content', 'manager_users', 'site_templates'];

    /**
     * このサイトの表でない表の名前。このサイトの接頭辞で始まらない表と（大文字小文字は問わない）、
     * 本体の表の名前で終わるのに「このサイトの接頭辞 + 本体の表の名前」でない表
     * （接頭辞が重なるダンプ、例えばこのサイトが modx_ でダンプが modx_old_。接頭辞が空のサイトへの modx_ のダンプ）
     */
    private static function foreignTables(array $tables): array
    {
        $prefix = (string) db()->table_prefix;
        return array_values(array_filter($tables, function ($table) use ($prefix) {
            if (strncasecmp($table, $prefix, strlen($prefix)) !== 0) {
                return true;
            }
            foreach (self::CORE_TABLES as $core) {
                if (strcasecmp(substr($table, -strlen($core)), $core) === 0 && strcasecmp($table, $prefix . $core) !== 0) {
                    return true;
                }
            }
            return false;
        }));
    }

    // 知らせに出すダンプの接頭辞。本体の表の名前から推し量れなければ null
    private static function dumpPrefix(array $tables)
    {
        foreach (self::CORE_TABLES as $core) {
            foreach ($tables as $table) {
                if (strcasecmp(substr($table, -strlen($core)), $core) === 0) {
                    return substr($table, 0, -strlen($core));
                }
            }
        }
        return null;
    }

    // system_settings が在るときだけ設定を戻してキャッシュを作り直す（無い DB では clearCache() が止まる）
    private function finish($keep)
    {
        if (!EvoDbTables::exists(EvoDbTables::name('system_settings'))) {
            return false;
        }
        $this->restoreSettings($keep);
        if (!EvoDbTables::exists(EvoDbTables::name('system_cache'))) {
            return false;
        }
        evo()->clearCache();
        return true;
    }

    // 制限時間は延ばすだけにする（残り時間を縮めない）
    private function extendTimeLimit()
    {
        $limit = (int)ini_get('max_execution_time');
        if ($limit !== 0 && $limit < 120) {
            @set_time_limit(120);
        }
    }

    // 取り込み先にある、環境ごとに違う設定の値
    private function getKeptSettings()
    {
        $keep = [];
        if (!EvoDbTables::exists(EvoDbTables::name('system_settings'))) {
            return $keep;
        }
        $keys = ['site_url', 'base_url', 'filemanager_path', 'rb_base_dir', 'fixed_site_url'];
        foreach ($keys as $key) {
            $value = db()->getValue(
                'setting_value',
                '[+prefix+]system_settings',
                sprintf("setting_name='%s'", db()->escape($key))
            );
            if ($value !== null) {
                $keep[$key] = $value;
            }
        }
        return $keep;
    }

    private function restoreSettings($keep)
    {
        $placeholders = [
            'site_url'         => ['[(site_url)]', MODX_SITE_URL],
            'base_url'         => ['[(base_url)]', MODX_BASE_URL],
            'filemanager_path' => ['[(base_path)]', MODX_BASE_PATH],
            'rb_base_dir'      => ['[(base_path)]', MODX_BASE_PATH],
        ];

        foreach ($placeholders as $key => $replace) {
            $where = sprintf("setting_name='%s'", db()->escape($key));
            if (isset($keep[$key])) {
                $value = $keep[$key];
            } else {
                $value = db()->getValue('setting_value', '[+prefix+]system_settings', $where);
                if ($value === null || strpos($value, $replace[0]) === false) {
                    continue;
                }
                $value = str_replace($replace[0], $replace[1], $value);
            }
            db()->update(
                db()->escape(['setting_value' => $value]),
                '[+prefix+]system_settings',
                $where
            );
        }

        // 取り込み先の値に戻す。ダンプに行が無くても戻し、取り込み先に無ければ消す
        db()->delete('[+prefix+]system_settings', "setting_name='fixed_site_url'");
        if (isset($keep['fixed_site_url'])) {
            db()->insert(
                db()->escape(['setting_name' => 'fixed_site_url', 'setting_value' => $keep['fixed_site_url']]),
                '[+prefix+]system_settings'
            );
        }
    }
}
