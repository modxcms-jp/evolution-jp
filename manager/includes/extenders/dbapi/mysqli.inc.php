<?php

class DBAPI
{

    public $conn = null;
    public $config;
    public $lastQuery;
    public $hostname;
    public $dbname;
    public $username;
    public $password;
    public $table_prefix;
    public $charset;
    public $connection_method;
    private $rs;
    private $rawQuery = false;

    /**
     * @name:  DBAPI
     *
     */
    function __construct(
        $host = '',
        $dbase = '',
        $user = '',
        $pwd = '',
        $prefix = null,
        $charset = 'utf8',
        $connection_method = 'SET CHARACTER SET'
    )
    {
        $this->config['host'] = $host ? $host : evo()->global_var('database_server', '');
        $this->config['dbase'] = $dbase ? $dbase : evo()->global_var('dbase', '');
        $this->config['user'] = $user ? $user : evo()->global_var('database_user', '');
        $this->config['pass'] = $pwd ? $pwd : evo()->global_var('database_password', '');
        $this->config['table_prefix'] = ($prefix !== null) ? $prefix : evo()->global_var('table_prefix');
        $this->config['charset'] = $charset ? $charset : evo()->global_var('database_connection_charset');
        $this->config['connection_method'] = evo()->global_var('database_connection_method', $connection_method);
        $this->hostname = &$this->config['host'];
        $this->dbname = &$this->config['dbase'];
        $this->username = &$this->config['user'];
        $this->password = &$this->config['pass'];
        $this->table_prefix = &$this->config['table_prefix'];
        $this->charset = &$this->config['charset'];
        $this->connection_method = &$this->config['connection_method'];
        $this->dbconnectionmethod = &$this->config['connection_method'];
    }

    public function set($prop_name, $value = null)
    {
        $this->$prop_name = $value;
        return $value;
    }

    public function get($prop_name, $default = null)
    {
        if (isset($this->$prop_name)) {
            return $this->$prop_name;
        }
        return $default;
    }

    public function prop($prop_name, $value = null)
    {
        if (strpos($prop_name, '*') === 0) {
            return $this->set(ltrim($prop_name, '*'), $value);
        }
        return $this->get($prop_name, $value);
    }

    /**
     * @name:  connect
     *
     */
    function connect($host = '', $uid = '', $pwd = '', $dbase = '', $tmp = 0)
    {
        if ($this->isConnected()) {
            return true;
        }

        if ($host) {
            $this->hostname = $host;
        }
        if ($uid) {
            $this->username = $uid;
        }
        if ($pwd) {
            $this->password = $pwd;
        }
        if ($dbase) {
            $this->dbname = $dbase;
        }

        if (substr(PHP_OS, 0, 3) === 'WIN' && $this->hostname === 'localhost') {
            $hostname = '127.0.0.1';
        } else {
            $hostname = $this->hostname;
        }
        if (!$this->hostname || !$this->username) {
            return false;
        }

        $tstart = evo()->getMicroTime();
        if (strpos($hostname, ':') !== false) {
            list($hostname, $port) = explode(':', $hostname);
            $this->conn = new mysqli($hostname, $this->username, $this->password, null, $port);
        } else {
            $this->conn = new mysqli($hostname, $this->username, $this->password);
        }
        if (!$this->conn) {
            return false;
        }
        if (isset($this->conn->connect_error) && $this->conn->connect_error) {
            $this->conn = null;
            if (evo()->config('send_errormail') && evo()->config('send_errormail') < 3) {
                evo()->sendmail(
                    'Missing to create the database connection! from ' . evo()->config('site_name')
                    , sprintf(
                        "%s\n%s\n%s\n%s\n%s%s(hostname)\n%s\n%s"
                        , 'Failed to create the database connection!'
                        , evo()->hsc($_SERVER['REQUEST_URI'], ENT_QUOTES)
                        , evo()->hsc(evo()->server('HTTP_USER_AGENT'), ENT_QUOTES)
                        , evo()->server('REMOTE_ADDR')
                        , evo()->server('REMOTE_HOST') ? evo()->server('REMOTE_HOST') . '(REMOTE_HOST)' . "\n" : ''
                        , gethostbyaddr(evo()->server('REMOTE_ADDR'))
                        , evo()->hsc(evo()->server('HTTP_REFERER'), ENT_QUOTES)
                        , date('Y-m-d H:i:s')
                    )
                );
            }
            return false;
        }
        if (!$this->isConnected()) {
            return false;
        }

        if ($this->dbname) {
            $this->dbname = trim($this->dbname, '` '); // remove the `` chars
            $rs = $this->select_db($this->dbname);
            if (!$rs) {
                evo()->messageQuit(
                    sprintf(
                        "Failed to select the database '%s'!"
                        , $this->dbname
                    )
                );
                return false;
            }
            $this->conn->query(sprintf('%s %s', $this->connection_method, $this->charset));
            $this->conn->set_charset($this->charset);
        }

        $tend = evo()->getMicroTime();
        $totaltime = $tend - $tstart;
        if (evo()->dumpSQL) {
            evo()->dumpSQLCode[] = sprintf(
                '<fieldset style="text-align:left;"><legend>Database connection</legend>%s</fieldset>'
                , sprintf("Database connection was created in %2.4f s", $totaltime)
            );
        }
        evo()->queryTime += $totaltime;
        return $this->conn;
    }

    function select_db($dbase = '')
    {
        if ($dbase) {
            return $this->conn->select_db($dbase);
        }
        return false;
    }

    /**
     * @name:  disconnect
     *
     */
    function disconnect()
    {
        $this->conn->close();
        $this->conn = null;
    }

    function escape($s, $safecount = 0)
    {
        $safecount++;
        if (1000 < $safecount) {
            exit(sprintf("Too many loops '%d'", $safecount));
        }

        if (!$this->isConnected()) {
            if (!$this->connect()) {
                return false;
            }
        }

        if (is_array($s)) {
            if (!$s) {
                return '';
            }

            foreach ($s as $i => $v) {
                $s[$i] = $this->escape($v, $safecount);
            }
            return $s;
        }

        if (function_exists('mysqli_set_charset')) {
            return $this->conn->real_escape_string($s);
        }

        if ($this->charset === 'utf8') {
            return mb_convert_encoding(
                $this->conn->real_escape_string(
                    mb_convert_encoding(
                        $s
                        , 'eucjp-win'
                        , 'utf-8'
                    )
                )
                , 'utf-8'
                , 'eucjp-win'
            );
        }

        return $this->conn->escape_string($s);
    }

    /**
     * @name:  query
     * @desc:  Mainly for internal use.
     * Developers should use select, update, insert, delete where possible
     */
    function query($sql, $watchError = true)
    {
        global $modx;
        if ($this->rawQuery) {
            echo $sql;
            return;
        }
        if (!$this->isConnected()) {
            return false;
        }

        $tstart = evo()->getMicroTime();

        if (is_array($sql)) {
            $sql = implode("\n", $sql);
        }

        $this->lastQuery = $sql;
        $result = $this->conn->query($sql);
        if (!$result) {
            if (!$watchError) {
                return false;
            }
            if (!in_array($this->conn->errno, [1064, 1054, 1060, 1061, 1091])) {
                evo()->messageQuit(
                    sprintf(
                        'Execution of a query to the database failed - %s'
                        , $this->getLastError()
                    )
                    , $sql
                );
                return false;
            }
            return true;
        }

        $totaltime = evo()->getMicroTime() - $tstart;
        $modx->queryTime = evo()->queryTime + $totaltime;
        $totaltime = $totaltime * 1000;
        if (evo()->dumpSQL) {
            $backtraces = debug_backtrace();
            array_shift($backtraces);
            $debug_path = [];
            foreach ($backtraces as $line) {
                $debug_path[] = $line['function'];
            }
            $_ = [];
            $_[] = sprintf(
                '<fieldset style="text-align:left"><legend>Query %d - %s</legend>'
                , evo()->executedQueries + 1
                , sprintf('%2.2f ms', $totaltime)
            );
            $_[] = $sql . '<br><br>';
            if (event()->name) {
                $_[] = 'Current Event  => ' . event()->name . '<br>';
            }
            if (event()->activePlugin) {
                $_[] = 'Current Plugin => ' . event()->activePlugin . '<br>';
            }
            if (evo()->currentSnippet) {
                $_[] = 'Current Snippet => ' . evo()->currentSnippet . '<br>';
            }
            if (stripos($sql, 'select') === 0) {
                $_[] = 'Record Count => ' . $this->getRecordCount($result) . '<br>';
            } else {
                $_[] = 'Affected Rows => ' . $this->getAffectedRows() . '<br>';
            }
            $_[] = 'Functions Path => ' . implode(' &gt; ', array_reverse($debug_path)) . '<br>';
            $_[] = '</fieldset><br />';
            $modx->dumpSQLCode[] = implode("\n", $_);
        }
        $modx->executedQueries = evo()->executedQueries + 1;
        return $result;
    }

    public function lastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * @name:  delete
     *
     */
    function delete($from, $where = '', $orderby = '', $limit = '')
    {
        if (!$from) {
            evo()->messageQuit('Empty $from parameters in DBAPI::delete().');
            return false;
        }
        if (!$where && !$limit) {
            $this->truncate($from);
        }
        if (is_array($where)) {
            $where = implode(' ', $where);
        }
        return $this->query(
            sprintf(
                'DELETE FROM %s %s %s %s'
                , $this->replaceFullTableName($from)
                , $where ? 'WHERE ' . $where : ''
                , $orderby ? 'ORDER BY ' . $orderby : ''
                , $limit !== '' ? 'LIMIT ' . $limit : ''
            )
        );
    }

    /**
     * @name:  select
     *
     */
    function select($fields = '*', $from = '', $where = '', $orderby = '', $limit = '')
    {
        if (!$from) {
            evo()->messageQuit('Empty $from parameters in DBAPI::select().');
            exit;
        }

        if (is_array($fields)) {
            $fields = $this->_getFieldsStringFromArray($fields);
        }
        if (is_array($from)) {
            $from = $this->_getFromStringFromArray($from);
        }
        if (is_array($where)) {
            $where = implode(' ', $where);
        }
        $rs = $this->query(
            sprintf(
                'SELECT %s FROM %s %s %s %s'
                , $this->replaceFullTableName($fields)
                , $this->replaceFullTableName($from)
                , trim($where) ? sprintf('WHERE %s', trim($where)) : ''
                , trim($orderby) ? sprintf('ORDER BY %s', $this->replaceFullTableName($orderby)) : ''
                , trim($limit) ? sprintf('LIMIT %s', $limit) : ''
            )
        );
        $this->rs = $rs;
        return $rs;
    }

    /**
     * @name:  update
     *
     */
    function update($fields, $table, $where = '', $orderby = '', $limit = '')
    {
        if (!$table) {
            evo()->messageQuit("Empty \$table parameter in DBAPI::update().");
            exit;
        }
        if (!is_array($fields)) {
            $pairs = $fields;
        } else {
            foreach ($fields as $key => $value) {
                if ($value === null || strtolower($value) === 'null') {
                    $value = 'NULL';
                } else {
                    $value = sprintf("'%s'", $value);
                }
                $pair[$key] = sprintf('`%s`=%s', $key, $value);
            }
            $pairs = implode(',', $pair);
        }
        if (is_array($where)) {
            $where = implode(' ', $where);
        }
        return $this->query(
            sprintf(
                'UPDATE %s SET %s %s %s %s'
                , $this->replaceFullTableName($table)
                , $pairs
                , $where ? 'WHERE ' . $where : ''
                , $orderby ? 'ORDER BY ' . $orderby : ''
                , $limit !== '' ? 'LIMIT ' . $limit : ''
            )
        );
    }

    /**
     * @name:  insert
     * @desc:  returns either last id inserted or the result from the query
     */
    function insert($fields, $intotable, $fromfields = '*', $fromtable = '', $where = '', $limit = '')
    {
        return $this->_insert('INSERT INTO', $fields, $intotable, $fromfields, $fromtable, $where, $limit);
    }

    /**
     * @name:  insert ignore
     * @desc:  returns either last id inserted or the result from the query
     */
    function insert_ignore($fields, $intotable, $fromfields = '*', $fromtable = '', $where = '', $limit = '')
    {
        return $this->_insert('INSERT IGNORE', $fields, $intotable, $fromfields, $fromtable, $where, $limit);
    }

    /**
     * @name:  replace
     * @desc:  returns either last id inserted or the result from the query
     */
    function replace($fields, $intotable, $fromfields = '*', $fromtable = '', $where = '', $limit = '')
    {
        return $this->_insert('REPLACE INTO', $fields, $intotable, $fromfields, $fromtable, $where, $limit);
    }

    function save($fields, $table, $where = '')
    {

        if (!$where || !$this->getRecordCount($this->select('*', $table, $where))) {
            $mode = 'insert';
        } else {
            $mode = 'update';
        }

        if ($mode === 'insert') {
            return $this->insert($fields, $table);
        }
        return $this->update($fields, $table, $where);
    }

    private function _insert(
        $insert_method = 'INSERT INTO',
        $fields,
        $intotable,
        $fromfields = '*',
        $fromtable = '',
        $where = '',
        $limit = ''
    )
    {
        if (!$intotable) {
            evo()->messageQuit('Empty $intotable parameters in DBAPI::insert().');
            return false;
        }

        $intotable = $this->replaceFullTableName($intotable);
        if ($fromtable) {
            $query = sprintf(
                '%s %s (%s) SELECT %s FROM %s %s %s'
                , $insert_method
                , $intotable
                , is_array($fields) ? implode(',', array_keys($fields)) : $fields
                , $fromfields ? $fromfields : $intotable
                , $this->replaceFullTableName($fromtable)
                , $where ? 'WHERE ' . $where : ''
                , $limit !== '' ? 'LIMIT ' . $limit : ''
            );
        } elseif (is_array($fields)) {
            if (!$fromtable) {
                $query = sprintf(
                    "%s %s (`%s`) VALUES('%s')"
                    , $insert_method
                    , $intotable
                    , implode('`,`', array_keys($fields))
                    , implode("','", array_values($fields))
                );
            }
        } else {
            $query = sprintf('%s %s %s', $insert_method, $intotable, $fields);
        }

        $rt = $this->query($query);
        if ($rt === false) {
            $result = false;
        } else {
            switch ($insert_method) {
                case 'INSERT IGNORE':
                case 'REPLACE INTO':
                    $result = $this->getAffectedRows() == 1 ? $this->getInsertId() : false;
                    break;
                case 'INSERT INTO':
                default:
                    $result = $this->getInsertId();
            }
        }
        if ($this->getInsertId() === false) {
            evo()->messageQuit("Couldn't get last insert key!");
        }
        return $result;
    } // __insert

    /**
     * @name:  freeResult
     *
     */
    function freeResult($rs)
    {
        $rs->free_result();
    }

    /**
     * @name:  fieldName
     *
     */
    function fieldName($rs, $col = 0)
    {
        return $rs->fetch_field_direct($col)->name;
    }

    /**
     * @name:  selectDb
     *
     */
    function selectDb($name)
    {
        $this->conn->select_db($name);
    }

    /**
     * @name:  getInsertId
     *
     */
    function getInsertId($conn = null)
    {
        if (!$this->isResult($conn)) {
            $conn =& $this->conn;
        }
        return $conn->insert_id;
    }

    /**
     * @name:  getAffectedRows
     *
     */
    function getAffectedRows($conn = null)
    {
        if (!$this->isResult($conn)) {
            $conn =& $this->conn;
        }
        return $conn->affected_rows;
    }

    /**
     * @name:  lastError
     *
     */
    function lastError($conn = null)
    {
        if (!$this->isResult($conn)) {
            $conn =& $this->conn;
        }
        return $conn->error;
    }

    function getLastError($conn = null)
    {
        return $this->lastError($conn);
    }

    function getLastErrorNo($conn = null)
    {
        if (!$this->isResult($conn)) {
            $conn =& $this->conn;
        }
        return $conn->errno;
    }

    /**
     * @name:  getRecordCount
     *
     */
    function count($rs = null, $from = '', $where = '')
    {
        if ($rs === null && $this->rs) {
            $rs = $this->rs;
        }
        if ($this->isResult($rs)) {
            return $rs->num_rows;
        }
        if (is_string($rs) && $where) {
            return $this->count(
                $this->select('*', $from, $where)
            );
        }
        return 0;
    }

    function getRecordCount($rs = null, $from = '', $where = '')
    {
        return $this->count($rs, $from, $where);
    }

    /**
     * @name:  getRow
     * @desc:  returns an array of column values
     * @param: $rs - dataset
     *
     */
    function getRow($param1 = null, $param2 = 'assoc', $where = '', $orderby = '', $limit = '')
    {
        if ($param1 === null && $this->rs) {
            $param1 = $this->rs;
        }
        if (is_string($param1)) {
            if ($where) {
                return $this->getRow(
                    $this->select($param1, $param2, $where, $orderby, $limit)
                    , 'assoc'
                );
            }
            return $this->getRow($this->query($param1), $param2);
        }

        if (!$this->isResult($param1)) {
            return false;
        }

        if ($param2 === 'assoc') {
            return $param1->fetch_assoc();
        }
        if ($param2 === 'num') {
            return $param1->fetch_row();
        }
        if ($param2 === 'object') {
            return $param1->fetch_object();
        }
        if ($param2 === 'both') {
            return $param1->fetch_array(MYSQLI_BOTH);
        }
        evo()->messageQuit(
            sprintf(
                "Unknown get type (%s) specified for fetchRow - must be empty, 'assoc', 'num' or 'both'."
                , $param2
            )
        );
        return false;
    }

    function getRows($param1, $param2 = 'assoc', $where = '', $orderby = '', $limit = '')
    {

        if (is_string($param1)) {
            if ($where) {
                return $this->getRows(
                    $this->select($param1, $param2, $where, $orderby, $limit)
                    , 'assoc'
                );
            }
            return $this->getRows($this->query($param1), $param2);
        }

        if (!$this->isResult($param1)) {
            return false;
        }

        $rs = $param1;
        $mode = $param2;

        if (!$this->count($rs)) {
            return [];
        }
        $_ = [];
        while ($row = $this->getRow($rs, $mode)) {
            $_[] = $row;
        }
        return $_;
    }

    /**
     * @name:  getColumn
     * @desc:  returns an array of the values found on colun $name
     * @param: $dsq - dataset or query string
     */
    function getColumn($name, $dsq)
    {
        if (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        if (!$dsq) {
            return [];
        }
        $col = [];
        while ($row = $this->getRow($dsq)) {
            $col[] = $row[$name];
        }
        return $col;
    }

    /**
     * @name:  getColumnNames
     * @desc:  returns an array containing the column $name
     * @param: $dsq - dataset or query string
     */
    function getColumnNames($dsq)
    {
        if (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        if (!$dsq) {
            return false;
        }
        $names = [];
        $limit = $this->numFields($dsq);
        for ($i = 0; $i < $limit; $i++) {
            $names[] = $this->fieldName($dsq, $i);
        }
        return $names;
    }

    /**
     * @name:  getValue
     * @desc:  returns the value from the first column in the set
     * @param: $rs - dataset or query string
     */
    function getValue($rs = null, $from = '', $where = '', $orderby = '', $limit = '')
    {
        if ($rs === null && $this->rs) {
            $rs = $this->rs;
        }
        if (is_string($rs)) {
            if ($from && $where) {
                $rs = $this->select($rs, $from, $where, $orderby, $limit);
            } else {
                $rs = $this->query($rs);
            }
        }
        $row = $this->getRow($rs, 'num');
        return $row[0];
    }

    /**
     * @name:  makeArray
     * @desc:  turns a recordset into a multidimensional array
     * @param: $rs Recordset to be packaged into an array
     * @return: an array of row arrays from recordset, or empty array
     *          if the recordset was empty, returns false if no recordset
     *          was passed
     */
    function makeArray($rs = '')
    {
        if (!$rs) {
            return false;
        }
        $rsArray = [];
        while ($row = $this->getRow($rs)) {
            $rsArray[] = $row;
        }
        return $rsArray;
    }

    /**
     * @name    getVersion
     * @desc    returns a string containing the database server version
     *
     * @return string
     */
    function getVersion()
    {
        if (!$this->isConnected()) {
            if (!$this->connect()) {
                return false;
            }
        }
        return $this->conn->server_info;
    }

    function server_info()
    {
        return $this->getVersion();
    }

    function host_info()
    {
        return $this->conn->host_info;
    }

    /**
     * @name  getObject
     * @desc  get row as object from table, like oop style
     *        $doc = $modx->db->getObject("site_content","id=1")
     *
     * @param string $table
     * @param string $where
     * @param string $orderby
     * @return array|bool|false|object|stdClass
     */
    function getObject($table, $where, $orderby = '')
    {
        $rs = $this->select(
            '*'
            , $this->replaceFullTableName($table, 'force')
            , $where
            , $orderby
            , 1
        );
        if ($this->getRecordCount($rs) == 0) {
            return false;
        }
        return $this->getRow($rs, 'object');
    }

    /**
     * @name getObjectSql
     * @desc  get row as object from sql query
     *
     * @param string $sql
     * @return array|bool|false|object|stdClass
     */
    function getObjectSql($sql)
    {
        $rs = $this->query($sql);
        if ($this->getRecordCount($rs) == 0) {
            return false;
        }
        return $this->getRow($rs, 'object');
    }

    /**
     * @name getObjects
     * @desc  get array of object by table or sql query
     *        $docs = $modx->db->getObjects("site_content","parent=1");
     *  or
     *        $docs = $modx->db->getObjects("select * from modx_site_content left join ...");
     *
     * @param type $sql_or_table
     * @param type $where
     * @param type $orderby
     * @param type $limit
     * @return array
     */
    function getObjects($sql_or_table, $where = '', $orderby = '', $limit = 0)
    {
        $sql_or_table = trim($sql_or_table);
        if (stripos($sql_or_table, 'select') === 0 || stripos($sql_or_table, 'show') === 0) {
            $sql = $sql_or_table;
        } else {
            $sql = sprintf(
                'SELECT * from %s %s %s %s'
                , $this->replaceFullTableName($sql_or_table, 'force')
                , $where ? ' WHERE ' . $where : ''
                , $orderby ? ' ORDER BY ' . $orderby : ''
                , $limit ? 'LIMIT ' . $limit : ''
            );
        }

        $rs = $this->query($sql);
        $result = [];
        while ($row = $this->getRow($rs, 'object')) {
            $result[] = $row;
        }
        return $result;

    }

    function isResult($rs)
    {
        return is_object($rs);
    }

    function getFullTableName($table_name)
    {
        return sprintf(
            '`%s`.`%s%s`'
            , trim($this->dbname, '`')
            , $this->table_prefix
            , $table_name
        );
    }

    /**
     * @name replaceFullTableName
     * @desc  Get full table name. Append table name and table prefix.
     *
     * @param string $table_name
     * @return string
     */
    function replaceFullTableName($table_name, $force = null)
    {
        if ($force) {
            return sprintf(
                '`%s`.`%s%s`'
                , trim($this->dbname, '`')
                , $this->table_prefix
                , str_replace('[+prefix+]', '', $table_name)
            );
        }

        if (strpos(trim($table_name), '[+prefix+]') !== false) {
            return preg_replace(
                '@\[\+prefix\+]([0-9a-zA-Z_]+)@'
                , sprintf(
                    '`%s`.`%s$1`'
                    , trim($this->dbname, '`')
                    , $this->table_prefix
                )
                , $table_name
            );
        }

        return $table_name;
    }

    /**
     * @name:  getXML
     * @desc:  returns an XML formay of the dataset $ds
     */
    function getXML($dsq)
    {
        if (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        $xmldata = [];
        while ($row = $this->getRow($dsq, 'both')) {
            $item = [];
            for ($j = 0; $line = each($row); $j++) {
                if ($j % 2) {
                    $item[] = sprintf(
                        "<%s>%s</%s>"
                        , $line[0]
                        , $line[1]
                        , $line[0]
                    );
                }
            }
            if ($item) {
                $xmldata[] = "<item>\r\n" . implode("\r\n", $item) . "</item>";
            }
        }
        return "<xml>\r\n<recordset>\r\n" . implode("\r\n", $xmldata) . "</recordset>\r\n</xml>";
    }

    /**
     * @name:  getTableMetaData
     * @desc:  returns an array of MySQL structure detail for each column of a
     *         table
     * @param: $table: the full name of the database table
     */
    function getTableMetaData($table)
    {
        if (!$table) {
            return false;
        }

        $ds = $this->query(sprintf('SHOW FIELDS FROM %s', $table));
        if (!$ds) {
            return false;
        }
        while ($row = $this->getRow($ds)) {
            $fieldName = $row['Field'];
            $metadata[$fieldName] = $row;
        }
        return $metadata;
    }

    /**
     * @name:  prepareDate
     * @desc:  prepares a date in the proper format for specific database types
     *         given a UNIX timestamp
     * @param: $timestamp: a UNIX timestamp
     * @param: $fieldType: the type of field to format the date for
     *         (in MySQL, you have DATE, TIME, YEAR, and DATETIME)
     */
    function prepareDate($timestamp, $fieldType = 'DATETIME')
    {
        if (!preg_match('@^[1-9][0-9]*$@', $timestamp)) {
            return '';
        }

        if ($fieldType === 'DATE') {
            return date('Y-m-d', $timestamp);
        }

        if ($fieldType === 'TIME') {
            return date('H:i:s', $timestamp);
        }

        if ($fieldType === 'YEAR') {
            return date('Y', $timestamp);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @name:  getHTMLGrid
     * @param: $params: Data grid parameters
     *         columnHeaderClass
     *         tableClass
     *         itemClass
     *         altItemClass
     *         columnHeaderStyle
     *         tableStyle
     *         itemStyle
     *         altItemStyle
     *         columns
     *         fields
     *         colWidths
     *         colAligns
     *         colColors
     *         colTypes
     *         cellPadding
     *         cellSpacing
     *         header
     *         footer
     *         pageSize
     *         pagerLocation
     *         pagerClass
     *         pagerStyle
     *
     */
    function getHTMLGrid($dsq, $params)
    {
        if (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        if ($dsq) {
            include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
            $grd = new DataGrid('', $dsq);

            $grd->noRecordMsg = $params['noRecordMsg'];

            $grd->columnHeaderClass = $params['columnHeaderClass'];
            $grd->cssClass = $params['cssClass'];
            $grd->itemClass = $params['itemClass'];
            $grd->altItemClass = $params['altItemClass'];

            $grd->columnHeaderStyle = $params['columnHeaderStyle'];
            $grd->cssStyle = $params['cssStyle'];
            $grd->itemStyle = $params['itemStyle'];
            $grd->altItemStyle = $params['altItemStyle'];

            $grd->columns = $params['columns'];
            $grd->fields = $params['fields'];
            $grd->colWidths = $params['colWidths'];
            $grd->colAligns = $params['colAligns'];
            $grd->colColors = $params['colColors'];
            $grd->colTypes = $params['colTypes'];
            $grd->colWraps = $params['colWraps'];

            $grd->cellPadding = $params['cellPadding'];
            $grd->cellSpacing = $params['cellSpacing'];
            $grd->header = $params['header'];
            $grd->footer = $params['footer'];
            $grd->pageSize = $params['pageSize'];
            $grd->pagerLocation = $params['pagerLocation'];
            $grd->pagerClass = $params['pagerClass'];
            $grd->pagerStyle = $params['pagerStyle'];

            return $grd->render();
        }
    }

    function optimize($table_name)
    {
        $rs = $this->query("OPTIMIZE TABLE ". $this->replaceFullTableName($table_name));
        if ($rs) {
            $rs = $this->query("ALTER TABLE " . $this->replaceFullTableName($table_name));
        }
        return $rs;
    }

    function truncate($table_name)
    {
        return $this->query("TRUNCATE TABLE " . $this->replaceFullTableName($table_name));
    }

    function dataSeek($result, $row_number)
    {
        return $result->data_seek($row_number);
    }

    function numFields($rs)
    {
        return $rs->field_count;
    }

    function importSql($source, $watchError = true)
    {
        if (is_file($source)) {
            $source = file_get_contents($source);
        }

        if (strpos($source, "\r") !== false) {
            $source = str_replace(["\r\n", "\r"], "\n", $source);
        }
        $_ = explode("\n", $source);
        $source = '';
        foreach ($_ as $v) {
            if (strpos($v, '#') === 0) {
                continue;
            }
            $source .= $v . "\n";
        }
        $sql_array = preg_split(
            '@;[ \t]*\n@'
            , str_replace('{PREFIX}', $this->table_prefix, $source)
        );
        foreach ($sql_array as $sql) {
            if (!trim($sql)) {
                continue;
            }
            $this->query($sql, $watchError);
        }
    }

    function table_exists($table_name)
    {
        $sql = sprintf(
            "SHOW TABLES FROM `%s` LIKE '%s'"
            , trim($this->dbname, '`')
            , str_replace('[+prefix+]', $this->table_prefix, $table_name)
        );

        return 0 < $this->getRecordCount($this->query($sql)) ? 1 : 0;
    }

    function field_exists($field_name, $table_name)
    {
        $table_name = $this->replaceFullTableName($table_name);

        if (!$this->table_exists($table_name)) {
            return 0;
        }

        return $this->getRow(
            $this->query(
                sprintf(
                    'DESCRIBE %s %s'
                    , $table_name
                    , $field_name
                )
            )
        ) ? 1 : 0;
    }

    function isConnected()
    {
        if (!$this->conn) {
            return false;
        }
        if (!$this->isResult($this->conn)) {
            return false;
        }
        return true;
    }

    function getCollation($table = '[+prefix+]site_content', $field = 'content')
    {
        $table = str_replace('[+prefix+]', $this->table_prefix, $table);
        $sql = sprintf("SHOW FULL COLUMNS FROM `%s`", $table);
        $rs = $this->query($sql);
        $Collation = 'utf8_general_ci';
        while ($row = $this->getRow($rs)) {
            if ($row['Field'] == $field && isset($row['Collation'])) {
                $Collation = $row['Collation'];
            }
        }
        return $Collation;
    }

    function _getFieldsStringFromArray($fields = [])
    {

        if (empty($fields)) {
            return '*';
        }

        $_ = [];
        foreach ($fields as $k => $v) {
            if (preg_match('@^[0-9]+$@', $k)) {
                $_[] = $v;
            } elseif ($k !== $v) {
                $_[] = sprintf("%s as '%s'", $v, $k);
            } else {
                $_[] = $v;
            }
        }
        return implode(',', $_);
    }

    function _getFromStringFromArray($tables = [])
    {
        $_ = [];
        foreach ($tables as $k => $v) {
            $_[] = $v;
        }
        return implode(' ', $_);
    }

    public function rawQuery($flag = true)
    {
        $this->rawQuery = $flag;
    }
}
