<?php
class DBAPI {

    var $conn;
    var $config;
    var $isConnected;
    var $lastQuery;
    var $hostname;
    var $dbname;
    var $username;
    var $password;
    var $table_prefix;
    var $charset;
    var $connection_method;
    
    /**
    * @name:  DBAPI
    *
    */
    function __construct($host='',$dbase='',$uid='',$pwd='',$prefix=NULL,$charset='utf8',$connection_method='SET CHARACTER SET') {
        global $table_prefix;
        if(!isset($GLOBALS['database_server']))   $GLOBALS['database_server'] = '';
        if(!isset($GLOBALS['dbase']))             $GLOBALS['dbase'] = '';
        if(!isset($GLOBALS['database_user']))     $GLOBALS['database_user'] = '';
        if(!isset($GLOBALS['database_password'])) $GLOBALS['database_password'] = '';
        $this->config['host']    = $host    ? $host    : $GLOBALS['database_server'];
        $this->config['dbase']   = $dbase   ? $dbase : $GLOBALS['dbase'];
        $this->config['user']    = $uid ? $uid : $GLOBALS['database_user'];
        $this->config['pass']    = $pwd ? $pwd : $GLOBALS['database_password'];
        $this->config['charset'] = $charset ? $charset : $GLOBALS['database_connection_charset'];
        $this->config['connection_method'] = isset($GLOBALS['database_connection_method']) ? $GLOBALS['database_connection_method'] : $connection_method;
        $this->dbconnectionmethod = &$this->config['connection_method'];
        $this->config['table_prefix'] = ($prefix !== NULL) ? $prefix : $GLOBALS['table_prefix'];
        $this->hostname          = &$this->config['host'];
        $this->dbname            = &$this->config['dbase'];
        $this->username          = &$this->config['user'];
        $this->password          = &$this->config['pass'];
        $this->table_prefix      = &$this->config['table_prefix'];
        $this->charset           = &$this->config['charset'];
        $this->connection_method = &$this->config['connection_method'];
    }
    
    /**
    * @name:  connect
    *
    */
    function connect($host = '', $dbase = '', $uid = '', $pwd = '', $tmp = 0) {
        global $modx;
        if($this->conn) return;
        if(!$host)  $host  = $this->hostname;
        if(substr(PHP_OS,0,3) === 'WIN' && $host==='localhost')
            $host = '127.0.0.1';
        if(!$dbase) $dbase = $this->dbname;
        $dbase   = trim($dbase, '`'); // remove the `` chars
        if(!$uid)   $uid   = $this->username;
        if(!$pwd)   $pwd   = $this->password;
        
        $tstart = $modx->getMicroTime();
        $safe_count = 0;
        do {
            $this->conn = new mysqli($host, $uid, $pwd, $dbase);
            if ($this->conn->connect_error) {
                $this->conn = null;
                if(isset($modx->config['send_errormail']) && $modx->config['send_errormail'] !== '0') {
                    if($modx->config['send_errormail'] <= 2) {
                        $logtitle    = 'Failed to create the database connection!';
                        $request_uri = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES);
                        $ua          = htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES);
                        $referer     = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES);
                        $ip          = $_SERVER['REMOTE_ADDR'];
                        $remote_host = $_SERVER['REMOTE_HOST'] ? $_SERVER['REMOTE_HOST'].'(REMOTE_HOST)'."\n" : '';
                        $hostname    = gethostbyaddr($ip);
                        $time = date('Y-m-d H:i:s');
                        $subject = 'Missing to create the database connection! from ' . $modx->config['site_name'];
                        $msg = "{$logtitle}\n{$request_uri}\n{$ua}\n{$ip}\n{$remote_host}{$hostname}(hostname)\n{$referer}\n{$time}";
                        $modx->sendmail($subject,$msg);
                    }
                }
                sleep(1);
                $safe_count++;
            }
        } while (!$this->conn && $safe_count<3);
        if(!$this->conn) {
            $modx->messageQuit('Failed to create the database connection!');
            exit;
        } else {
            $this->conn->query("{$this->connection_method} {$this->charset}");
            $tend = $modx->getMicroTime();
            $totaltime = $tend - $tstart;
            if ($modx->dumpSQL) {
                $msg = sprintf("Database connection was created in %2.4f s", $totaltime);
                $modx->queryCode .= '<fieldset style="text-align:left;"><legend>Database connection</legend>' . "{$msg}</fieldset>";
            }
            $this->conn->set_charset($this->charset);
            $this->isConnected = true;
            $modx->queryTime += $totaltime;
        }
    }
    
    /**
    * @name:  disconnect
    *
    */
    function disconnect() {
        $this->conn->close();
        $this->conn = null;
        $this->isConnected = false;
    }
    
    function escape($s, $safecount=0) {
        $safecount++;
        if(1000<$safecount) exit("Too many loops '{$safecount}'");
        if (empty ($this->conn) || !is_object($this->conn)) {
            $this->connect();
        }
        
        if(is_array($s)) {
            if(count($s) === 0) {
$s = '';
            } else {
                foreach($s as $i=>$v) {
                  $s[$i] = $this->escape($v,$safecount);
                }
            }
        }
        elseif (function_exists('mysqli_set_charset') && $this->conn)
        {
            $s = $this->conn->real_escape_string($s);
        }
        elseif ($this->charset=='utf8' && $this->conn)
        {
            $s = mb_convert_encoding($s, 'eucjp-win', 'utf-8');
            $s = $this->conn->real_escape_string($s);
            $s = mb_convert_encoding($s, 'utf-8', 'eucjp-win');
        }
        else
        {
            $s = $this->conn->escape_string($s);
        }
        return $s;
    }
    
    /**
    * @name:  query
    * @desc:  Mainly for internal use.
    * Developers should use select, update, insert, delete where possible
    */
    function query($sql,$watchError=true) {
        global $modx;
        if (empty ($this->conn) || !is_object($this->conn)) {
            $this->connect();
        }
        $tstart = $modx->getMicroTime();
        $this->lastQuery = $sql;
        $result = $this->conn->query($sql);
        if (!$result) {
            switch($this->conn->connect_errno) {
                case 1060:
                case 1061:
                case 1091:
                    if(!$watchError) break;
                default:
                    $modx->messageQuit('Execution of a query to the database failed - ' . $this->getLastError(), $sql);
            }
        } else {
            $tend = $modx->getMicroTime();
            $totaltime = $tend - $tstart;
            $modx->queryTime = $modx->queryTime + $totaltime;
            if ($modx->dumpSQL) {
                $backtraces = debug_backtrace();
                array_shift($backtraces);    
                $debug_path = array();
                foreach ($backtraces as $line) $debug_path[] = $line['function'];
                $debug_path = implode(' > ', array_reverse($debug_path));
                $modx->queryCode .= "<fieldset style='text-align:left'><legend>Query " . ($modx->executedQueries + 1) . " - " . sprintf("%2.2f ms", $totaltime*1000) . "</legend>";
                $modx->queryCode .= $sql . '<br><br>';
                if ($modx->event->name) $modx->queryCode .= 'Current Event  => ' . $modx->event->name . '<br>';
                if ($modx->event->activePlugin) $modx->queryCode .= 'Current Plugin => ' . $modx->event->activePlugin . '<br>';
                if ($modx->currentSnippet) $modx->queryCode .= 'Current Snippet => ' . $modx->currentSnippet . '<br>';
                if (stripos($sql, 'select')===0) $modx->queryCode .= 'Record Count => ' . $this->getRecordCount($result) . '<br>';
                else $modx->queryCode .= 'Affected Rows => ' . $this->getAffectedRows() . '<br>';
                $modx->queryCode .= 'Functions Path => ' . $debug_path . '<br>';
                $modx->queryCode .= "</fieldset><br />";
            }
            $modx->executedQueries = $modx->executedQueries + 1;
            return $result;
        }
    }
    
    /**
    * @name:  delete
    *
    */
    function delete($from,$where='',$orderby='', $limit = '') {
        global $modx;
        if (!$from) {
            $modx->messageQuit("Empty \$from parameters in DBAPI::delete().");
        } else {
            $from = $this->replaceFullTableName($from);
            if($where != '') $where = "WHERE {$where}";
            if($orderby !== '') $orderby = "ORDER BY {$orderby}";
            if($limit != '') $limit = "LIMIT {$limit}";
            return $this->query("DELETE FROM {$from} {$where} {$orderby} {$limit}");
        }
    }
    
    /**
    * @name:  select
    *
    */
    function select($fields = '*', $from = '', $where = '', $orderby = '', $limit = '') {
        global $modx;
        if (!$from) {
            $modx->messageQuit("Empty \$from parameters in DBAPI::select().");
        } else {
            $from = $this->replaceFullTableName($from);
            if($where !== '')   $where   = "WHERE {$where}";
            if($orderby !== '') $orderby = "ORDER BY {$orderby}";
            if($limit !== '')   $limit   = "LIMIT {$limit}";
            return $this->query("SELECT {$fields} FROM {$from} {$where} {$orderby} {$limit}");
        }
    }
    
    /**
    * @name:  update
    *
    */
    function update($fields, $table, $where = '', $orderby='', $limit='') {
        global $modx;
        if (!$table) {
            $modx->messageQuit("Empty \$table parameter in DBAPI::update().");
        } else {
            $table = $this->replaceFullTableName($table);
            if (!is_array($fields)) $pairs = $fields;
            else {
                foreach ($fields as $key => $value) {
                    if(is_null($value) || strtolower($value) === 'null'){
                        $value = 'NULL';
                    }else{
                        $value = "'{$value}'";
                    }
                    $pair[$key] = "`{$key}`={$value}";
                }
                $pairs = join(',',$pair);
            }
            if($where != '') $where = "WHERE {$where}";
            if($orderby !== '') $orderby = "ORDER BY {$orderby}";
            if($limit !== '')   $limit   = "LIMIT {$limit}";
            return $this->query("UPDATE {$table} SET {$pairs} {$where} {$orderby} {$limit}");
        }
    }
    
    /**
    * @name:  insert
    * @desc:  returns either last id inserted or the result from the query
    */
    function insert($fields, $intotable, $fromfields = '*', $fromtable = '', $where = '', $limit = '') {
        return $this->__insert('INSERT INTO', $fields, $intotable, $fromfields, $fromtable, $where, $limit);
    }
    
    /**
    * @name:  insert ignore
    * @desc:  returns either last id inserted or the result from the query
    */
    function insert_ignore($fields, $intotable, $fromfields = '*', $fromtable = '', $where = '', $limit = '') {
        return $this->__insert('INSERT IGNORE', $fields, $intotable, $fromfields, $fromtable, $where, $limit);
    }
    
    /**
    * @name:  replace
    * @desc:  returns either last id inserted or the result from the query
    */
    function replace($fields, $intotable, $fromfields = '*', $fromtable = '', $where = '', $limit = '') {
        return $this->__insert('REPLACE INTO', $fields, $intotable, $fromfields, $fromtable, $where, $limit);
    }
    
    private function __insert($insert_method='INSERT INTO', $fields, $intotable, $fromfields = '*', $fromtable = '', $where = '', $limit = '') {
        global $modx;
        if (!$intotable) {
            $modx->messageQuit('Empty $intotable parameters in DBAPI::insert().');
        } else {
            $intotable = $this->replaceFullTableName($intotable);
            $fromtable = $this->replaceFullTableName($fromtable);
            if (!is_array($fields))
            {
                $pairs = $fields;
            }
            else
            {
                $keys = array_keys($fields);
                $keys = implode(',', $keys) ;
                $values = array_values($fields);
                $values = implode("','", $values);
                if (!$fromtable && $values) $pairs = "({$keys}) VALUES('{$values}')";
            }
            if($fromtable)
            {
                if(empty($fromfields)) $fromfields = $intotable;
                if (is_array($fields))
                {
                    $keys   = array_keys($fields);
                    $fields = implode(',', $keys);
                }
                if ($where !== '') $where = "WHERE {$where}";
                if ($limit !== '') $limit = "LIMIT {$limit}";
                
                $query = "{$insert_method} {$intotable} ({$fields}) SELECT {$fromfields} FROM {$fromtable} {$where} {$limit}";
            }
            else $query = "{$insert_method} {$intotable} {$pairs}";
            
            $rt = $this->query($query);
            if($rt===false) $result = false;
            else
            {
                switch($insert_method)
                {
                    case 'INSERT IGNORE':
                    case 'REPLACE INTO':
                        $diff = $this->getAffectedRows();
                        if($diff==1) $result = $this->getInsertId();
                        else         $result = false;
                        break;
                    case 'INSERT INTO':
                    default:
                        $result = $this->getInsertId();
                }
            }
        }
        if (($lid = $this->getInsertId())===false) $modx->messageQuit("Couldn't get last insert key!");
        return $result;
    } // __insert
    
    /**
    * @name:  freeResult
    *
    */
    function freeResult($rs) {
        $rs->free_result();
    }
    
    /**
    * @name:  fieldName
    *
    */
    function fieldName($rs,$col=0) {
        $field = $rs->fetch_field_direct($col);
        return $field->name;
    }
    
    /**
    * @name:  selectDb
    *
    */
    function selectDb($name) {
        $this->conn->select_db($name);
    }

    /**
    * @name:  getInsertId
    *
    */
    function getInsertId($conn=NULL) {
        if (!is_object($conn)) $conn =& $this->conn;
        return $conn->insert_id;
    }
    
    /**
    * @name:  getAffectedRows
    *
    */
    function getAffectedRows($conn=NULL) {
        if (!is_object($conn)) $conn =& $this->conn;
        return $conn->affected_rows;
    }
    
    /**
    * @name:  getLastError
    *
    */
    function getLastError($conn=NULL) {
        if (!is_object($conn)) $conn =& $this->conn;
        return $conn->error;
    }
    
    /**
    * @name:  getRecordCount
    *
    */
    function getRecordCount($ds) {
        return ($this->isResult($ds)) ? $ds->num_rows : 0;
    }
    
    /**
    * @name:  getRow
    * @desc:  returns an array of column values
    * @param: $dsq - dataset
    *
    */
    function getRow($ds, $mode = 'assoc') {
        if ($this->isResult($ds)) {
            switch($mode) {
                case 'assoc' :return $ds->fetch_assoc();
                case 'num'   :return $ds->fetch_row();
                case 'object':return $ds->fetch_object();
                case 'both'  :return $ds->fetch_array(MYSQLI_BOTH);
                default      :
                    global $modx;
                    $modx->messageQuit("Unknown get type ({$mode}) specified for fetchRow - must be empty, 'assoc', 'num' or 'both'.");
            }
        }
    }
    
    /**
    * @name:  getColumn
    * @desc:  returns an array of the values found on colun $name
    * @param: $dsq - dataset or query string
    */
    function getColumn($name, $dsq) {
        if (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        if ($dsq) {
            $col = array ();
            while ($row = $this->getRow($dsq)) {
                $col[] = $row[$name];
            }
            return $col;
        }
        else return array ();
    }
    
    /**
    * @name:  getColumnNames
    * @desc:  returns an array containing the column $name
    * @param: $dsq - dataset or query string
    */
    function getColumnNames($dsq) {
        if (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        if ($dsq) {
            $names = array ();
            $limit = $this->numFields($dsq);
            for ($i = 0; $i < $limit; $i++) {
                $names[] = $this->fieldName($dsq, $i);
            }
            return $names;
        }
    }
        
    /**
    * @name:  getValue
    * @desc:  returns the value from the first column in the set
    * @param: $dsq - dataset or query string
    */
    function getValue($dsq, $from='', $where='') {
        if($from!=='' && $where!=='') {
            $from = str_replace('[+prefix+]', '', $from);
            $rs = $this->getObject($from,$where);
            if(isset($rs->$dsq)) return $rs->$dsq;
        }
        elseif (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        if ($this->isResult($dsq)) {
            $r = $this->getRow($dsq, 'num');
            return $r[0];
        }
    }
    
    /**
    * @name:  makeArray
    * @desc:  turns a recordset into a multidimensional array
    * @return: an array of row arrays from recordset, or empty array
    *          if the recordset was empty, returns false if no recordset
    *          was passed
    * @param: $rs Recordset to be packaged into an array
    */
    function makeArray($rs='') {
        if(!$rs) return false;
        $rsArray = array();
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
    function getVersion() {
        return $this->conn->server_info;
    }
    
    function server_info() {
        return $this->getVersion();
    }
    
    function host_info() {
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
     * @return an object of row from query, or return false if empty query    
     */
    function getObject($table,$where,$orderby=''){
        $table = $this->replaceFullTableName($table,'force');
        $rs = $this->select('*', $table, $where, $orderby, 1);
        if ($this->getRecordCount($rs)==0) return false;
        return $this->getRow($rs,'object');
    }

    /**
     * @name getObjectSql
     * @desc  get row as object from sql query
     * 
     * @param string $sql
     * @return an object of row from query, or return false if empty query     
     */
    function getObjectSql($sql){
        $rs = $this->query($sql);
        if ($this->getRecordCount($rs)==0) return false;
        return $this->getRow($rs,'object');
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
     * @return type 
     */
    function getObjects($sql_or_table,$where='',$orderby='',$limit=0){
        $sql_or_table = trim($sql_or_table);
        if ((stripos($sql_or_table, 'select')===0)||(stripos($sql_or_table, 'show')===0)){
            $sql = $sql_or_table;
        }else{
            $where = empty($where) ? '' : " WHERE {$where}";
            $orderby = empty($orderby)?"":" ORDER BY {$orderby}";
            $limit = empty($limit)?"": "LIMIT {$limit}";
            $sql_or_table = $this->replaceFullTableName($sql_or_table,'force');
            $sql = "SELECT * from {$sql_or_table} {$where} {$orderby} {$limit}";
        }

        $rs = $this->query($sql);
        $result = array();
        while ($row = $this->getRow($rs,'object')){
            $result[] = $row;
        }
        return $result;

    }
    
    function isResult($rs) {
        return is_object($rs);
    }

    function getFullTableName($table_name) {
        $dbase = trim($this->dbname,'`');
        return "`{$dbase}`.`{$this->table_prefix}{$table_name}`";
    }
    
    /**
     * @name replaceFullTableName
     * @desc  Get full table name. Append table name and table prefix.
     * 
     * @param string $table_name
     * @return string 
     */
    function replaceFullTableName($table_name,$force=null) {
        $table_name = trim($table_name);
        $dbase  = trim($this->dbname,'`');
        $prefix = $this->table_prefix;
        if(!empty($force)) {
            $table_name = str_replace('[+prefix+]','',$table_name);
            $result = "`{$dbase}`.`{$prefix}{$table_name}`";
        } elseif(strpos($table_name,'[+prefix+]')!==false) {
            $result = preg_replace('@\[\+prefix\+\]([0-9a-zA-Z_]+)@', "`{$dbase}`.`{$prefix}$1`", $table_name);
        }
        else $result = $table_name;
        
        return $result;
    }
    
    /**
    * @name:  getXML
    * @desc:  returns an XML formay of the dataset $ds
    */
    function getXML($dsq) {
        if (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        $xmldata = "<xml>\r\n<recordset>\r\n";
        while ($row = $this->getRow($dsq, 'both')) {
            $xmldata .= "<item>\r\n";
            for ($j = 0; $line = each($row); $j++) {
                if ($j % 2) {
                    $xmldata .= "<{$line[0]}>{$line[1]}</{$line[0]}>\r\n";
                }
            }
            $xmldata .= "</item>\r\n";
        }
        $xmldata .= "</recordset>\r\n</xml>";
        return $xmldata;
    }
    
    /**
    * @name:  getTableMetaData
    * @desc:  returns an array of MySQL structure detail for each column of a
    *         table
    * @param: $table: the full name of the database table
    */
    function getTableMetaData($table) {
        $metadata = false;
        if (!empty ($table)) {
            $sql = "SHOW FIELDS FROM {$table}";
            if ($ds = $this->query($sql)) {
                while ($row = $this->getRow($ds)) {
                    $fieldName = $row['Field'];
                    $metadata[$fieldName] = $row;
                }
            }
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
    function prepareDate($timestamp, $fieldType = 'DATETIME') {
        $date = '';
        if ($timestamp !== false && $timestamp > 0) {
            switch ($fieldType) {
                case 'DATE' :
                $date = date('Y-m-d', $timestamp);
                break;
                case 'TIME' :
                $date = date('H:i:s', $timestamp);
                break;
                case 'YEAR' :
                $date = date('Y', $timestamp);
                break;
                case 'DATETIME' :
                default :
                $date = date('Y-m-d H:i:s', $timestamp);
                break;
            }
        }
        return $date;
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
    function getHTMLGrid($dsq, $params) {
        if (!$this->isResult($dsq)) {
            $dsq = $this->query($dsq);
        }
        if ($dsq) {
            include_once (MODX_CORE_PATH . 'controls/datagrid.class.php');
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
    
    function optimize($table_name) {
        $table_name = str_replace('[+prefix+]', $this->table_prefix, $table_name);
        $rs = $this->query("OPTIMIZE TABLE `{$table_name}`");
        if($rs) $rs = $this->query("ALTER TABLE `{$table_name}`");
        return $rs;
    }

    function truncate($table_name) {
        $table_name = str_replace('[+prefix+]', $this->table_prefix, $table_name);
        $rs = $this->query("TRUNCATE TABLE `{$table_name}`");
        return $rs;
    }
    
    function numFields($rs) {
        return $rs->field_count;
    }

    function importSql($source,$watchError=true)
    {
        global $modx;
        
        if(is_file($source)) $source = file_get_contents($source);
        
        if(strpos($source, "\r")!==false) $source = str_replace(array("\r\n","\r"),"\n",$source);
        $source = str_replace('{PREFIX}',$this->table_prefix,$source);
        $sql_array = preg_split('@;[ \t]*\n@', $source);
        foreach($sql_array as $sql_entry)
        {
            $sql_entry = trim($sql_entry);
            if(empty($sql_entry)) continue;
            $rs = $modx->db->query($sql_entry,$watchError);
        }
    }
    
    function table_exists($table_name)
    {
        $dbname = trim($this->dbname,'`');
        $table_name = str_replace('[+prefix+]',$this->table_prefix,$table_name);
        $sql = sprintf("SHOW TABLES FROM `%s` LIKE '%s'", $dbname, $table_name);
        $rs = $this->query($sql);
        
        return 0<$this->getRecordCount($rs) ? 1 : 0;
    }
    
    function field_exists($field_name,$table_name)
    {
        $table_name = $this->replaceFullTableName($table_name);
        
        if(!$this->table_exists($table_name)) return 0;
        
        $rs = $this->query("DESCRIBE {$table_name} {$field_name}");
        
        return $this->getRow($rs) ? 1 : 0;
    }
}
