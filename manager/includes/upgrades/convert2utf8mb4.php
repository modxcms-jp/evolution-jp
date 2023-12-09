<?php
$convert = new convert2utf8mb4();
if (!$convert->isAvailable()) {
    return false;
}

$convert->replaceConfig();
$convert->convertDb();
$convert->convertTables();

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

    public function replaceConfig()
    {
        if (strpos($this->getConfigContent(), "'utf8mb4'") !== false) {
            return;
        }

        @chmod($this->config_path, 0666);
        file_put_contents(
            $this->config_path,
            str_replace("'utf8'", "'utf8mb4'", $this->getConfigContent())
        );
        @chmod($this->config_path, 0444);
    }

    public function convertDb()
    {
        $rs = db()->select(
            'DEFAULT_CHARACTER_SET_NAME',
            'INFORMATION_SCHEMA.SCHEMATA',
            sprintf("SCHEMA_NAME = '%s'", db()->dbase)
        );
        $charset = db()->getValue($rs);
        if(!$charset) {
            return;
        }

        if ($charset !== 'utf8mb4') {
            db()->exec(
                sprintf(
                    "ALTER DATABASE %s CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci",
                    db()->dbase
                )
            );
            // echo "Database charset has been changed to utf8mb4.<br>\n";
        } else {
            // echo "Database charset is already utf8mb4.<br>\n";
        }
    }

    public function convertTables()
    {
        $rs = db()->select(
            'TABLE_NAME, CCSA.CHARACTER_SET_NAME',
            [
                'INFORMATION_SCHEMA.TABLES AS T',
                'JOIN INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY AS CCSA ON T.TABLE_COLLATION = CCSA.COLLATION_NAME'
            ],
            [
                sprintf(
                    "TABLE_SCHEMA = '%s' AND TABLE_NAME LIKE '%s%%'",
                    db()->dbase,
                    db()->table_prefix
                ),
                "AND CCSA.CHARACTER_SET_NAME != 'utf8mb4'"
            ]
        );
        while($row = db()->getRow($rs)) {
            db()->exec(
                sprintf(
                    "ALTER TABLE %s CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",
                    $row['TABLE_NAME']
                )
            );
            // echo "Table {$row['TABLE_NAME']} charset has been changed to utf8mb4.<br>\n";
        }
    }
}
