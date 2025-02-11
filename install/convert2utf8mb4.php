<?php
class convert2utf8mb4 {
    private $config_path;

    public function __construct()
    {
        $this->config_path = MODX_CORE_PATH . 'config.inc.php';
    }

    public function getConfigContent()
    {
        static $content = null;
        if (!$content) {
            $content = file_get_contents($this->config_path);
        }
        return $content;
    }

    public function isAvailable()
    {
        $rs = db()->exec("SHOW CHARACTER SET LIKE 'utf8mb4'");
        return db()->count($rs);
    }

    public function updateConfigIncPhp()
    {
        if ($this->isUtf8mb4Configured()) {
            return false;
        }

        @chmod($this->config_path, 0666);
        return file_put_contents(
            $this->config_path,
            str_replace("'utf8'", "'utf8mb4'", $this->getConfigContent())
        );
    }

    public function isUtf8mb4Configured() {
        return strpos($this->getConfigContent(), "'utf8mb4'") !== false;
    }

    public function convertDb()
    {
        db()->exec(
            sprintf(
                "ALTER DATABASE %s CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci",
                db()->dbase
            )
        );
    }

    public function getDefaultCharset() {
        $rs = db()->select(
            'DEFAULT_CHARACTER_SET_NAME',
            'INFORMATION_SCHEMA.SCHEMATA',
            sprintf("SCHEMA_NAME = '%s'", db()->dbase)
        );
        return db()->getValue($rs);
    }

    public function convertTablesWithPrefix($prefix)
    {
        $targetTables = [
            'active_users',
            'categories',
            'document_groups',
            'documentgroup_names',
            'event_log',
            'manager_log',
            'manager_users',
            'member_groups',
            'membergroup_access',
            'membergroup_names',
            'site_content',
            'site_htmlsnippets',
            'site_module_access',
            'site_module_depobj',
            'site_modules',
            'site_plugin_events',
            'site_plugins',
            'site_revision',
            'site_snippets',
            'site_templates',
            'site_tmplvar_access',
            'site_tmplvar_contentvalues',
            'site_tmplvar_templates',
            'site_tmplvars',
            'system_cache',
            'system_eventnames',
            'system_settings',
            'user_attributes',
            'user_messages',
            'user_roles',
            'user_settings',
            'web_groups',
            'web_user_attributes',
            'web_user_settings',
            'web_users',
            'webgroup_access',
            'webgroup_names'
        ];

        $rs = db()->select(
            'TABLE_NAME, CCSA.CHARACTER_SET_NAME, T.TABLE_COLLATION',
            [
                'INFORMATION_SCHEMA.TABLES AS T',
                'JOIN INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY AS CCSA ON T.TABLE_COLLATION = CCSA.COLLATION_NAME'
            ],
            [
                sprintf(
                    "TABLE_SCHEMA = '%s' AND TABLE_NAME LIKE '%s%%'",
                    db()->dbase,
                    $prefix
                ),
                "AND CCSA.COLLATION_NAME != 'utf8mb4_general_ci'"
            ]
        );

        while($row = db()->getRow($rs)) {
            $tableNameWithoutPrefix = str_replace($prefix, '', $row['TABLE_NAME']);
            if (in_array($tableNameWithoutPrefix, $targetTables)) {
                echo sprintf('%sを変換します<br>', $row['TABLE_NAME']);
                db()->exec(
                    sprintf(
                        "ALTER TABLE %s CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",
                        $row['TABLE_NAME']
                    )
                );
                // echo "Table {$row['TABLE_NAME']} charset has been changed to utf8mb4.<br>\n";
            }
        }
        return db()->count($rs);
    }

    public function getDbCollation()
    {
        $rs = db()->select(
            'DEFAULT_COLLATION_NAME',
            'INFORMATION_SCHEMA.SCHEMATA',
            sprintf("SCHEMA_NAME = '%s'", db()->dbase)
        );
        return db()->getValue($rs);
    }
}
