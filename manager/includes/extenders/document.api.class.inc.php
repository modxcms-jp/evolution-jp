<?php
/*
 * API for Resource(Document)
 *
 * リソース(ドキュメント)に対する編集機能を提供します。
 *
 */

class Document
{

	const LOG_INFO = 1;
	const LOG_WARN = 2;
	const LOG_ERR  = 3;

	//private $id='';                  // Resource ID
	private $content = array();        // Site content
	private $tv      = array();        // Template Value
	private $logLevel = self::LOG_ERR; // Output log level
	private $lastLog = '';             // Last log message
	private $modx;

	//content table column (name => default value(null=sql default))
	private $content_lists
		= array('id' => null ,
				'type' => null ,
				'contentType' => null ,
				'pagetitle' => '' ,
				'longtitle' => '' ,
				'description' => '' ,
				'alias' => '' ,
				'link_attributes' => '' ,
				'published' => null ,
				'pub_date' => null ,
				'unpub_date' => null ,
				'parent' => null ,
				'isfolder' => null ,
				'introtext' => null ,
				'content' => null ,
				'richtext' => null ,
				'template' => null ,
				'menuindex' => 'auto' ,
				'searchable' => null ,
				'cacheable' => null ,
				'createdby' => null ,
				'createdon' => 'now()' ,
				'editedby' => null ,
				'editedon' => 'now()' ,
				'deleted' => null ,
				'deletedon' => null ,
				'deletedby' => null ,
				'publishedon' => null ,
				'publishedby' => null ,
				'menutitle' => '' ,
				'donthit' => null ,
				'haskeywords' => null ,
				'hasmetatags' => null ,
				'privateweb' => null ,
				'privatemgr' => null ,
				'content_dispo' => null ,
				'hidemenu' => null);

	//日付処理が必要なカラム
	private $content_type_date = array('pub_date','unpub_date','createdon','editedon','deletedon');
    
	/*
	 * __construct
	 *
	 * @param $id    Resource ID(blank=New resource)
	 * @param $level Log level
	 * @return none
	 *
	 */
	public function __construct($id='',$level=''){
		global $modx;

		if( $this->isInt($level,1) )
			$this->logLevel = $level;

		$this->modx = &$modx;
		if( empty($id) ){
			$this->content = $this->content_lists;
			$this->tv = array();
		}else{
			$this->load($id);
		}
	}

	/*
	 * リソース値の取得
	 *
	 * fieldの先頭に「tv.」をつけるとTVを取得する
	 *
	 * @param $field Resource column name
	 * @return string
	 *
	 */
	public function get($field='content'){
		if( !empty($field) && array_key_exists($field,$this->content) ){
			return $this->content[$field];
		}
		$field = $this->getTVName($field);
		if( $field !== false ){			
			return $this->getTV($field);
		}
		return false;
	}

	/*
	 * TV取得(名前ベース)
	 *
	 * 同じ名前のTVは通常存在しない
	 * get()と違い先頭に「tv.」があってはいけない
	 *
	 * @param $name TV名
	 * @return string/false
	 *
	 */
	public function getTV($name){
		foreach( $this->tv as $k => $v ){
			if( $v['name'] == $name ){
				return $v['value'];
			}
		}
		return false;
	}

	/*
	 * TV取得(idベース)
	 *
	 * @param $id TVID
	 * @return string/false
	 *
	 */
	public function getTVbyID($id){
		if( !empty($id) && array_key_exists($id,$this->tv) ){
			return $this->tv[$id]['value'];
		}
		return false;					  
	}

	/*
	 * すべてのTV取得
	 *
	 * フォーマットは次のとおり。
	 *    [TVID]       ... TVID
	 *       [name]    ... TV名
	 *       [value]   ... TV値
	 *       [default] ... TVデフォルト値
	 *
	 * @param none
	 * @return string/false
	 *
	 */
	public function getAllTVs(){
		return $this->tv;
	}

	/*
	 * TVID取得
	 *
	 * 先頭に「tv.」はつけてはいけない
	 * 
	 * @param $name テンプレート変数名
	 * @return int/false
	 *
	 */
	public function getTVID($name){
		foreach( $this->tv as $k => $v ){
			if( $v['name'] == $name ){
				return $k;
			}
		}
		return false;
	}

	/*
	 * リソースの値設定
	 *
	 * fieldの先頭に「tv.」をつけるとTVに設定する
	 *
	 * @param $field Resource column name
	 * @param $val   new value
	 * @return bool
	 *
	 */
	public function set($field='content',$val=''){
		if( array_key_exists($field,$this->content_lists) ){
			$tmp = $this->content['template'];
			$this->content[$field] = $val;
			if( $tmp != $this->content['template'] ){
				return $this->setTemplatebyID($val);
			}
			return true;
		}
		$field = $this->getTVName($field);
		if( $field !== false ){
			return $this->setTV($field,$val);
		}
		$this->logWarn('Field not exist:'.$field);
		return true;
	}

	/*
	 * TV設定
	 *
	 * @param $name TV名
	 * @param $val  値(無指定、もしくはnull指定でデフォルト値に戻す)
	 * @return bool
	 *
	 */
	public function setTV($name,$val=null){
		foreach( $this->tv as $k => $v ){			
			if( $v['name'] == $name ){
				return $this->setTVbyID($k,$val);
			}
		}
		$this->logWarn('TV not exist:'.$name);
		return false;
	}

	/*
	 * TV設定(idベース)
	 *
	 * @param $id   TVID
	 * @param $val  値(無指定、もしくはnull指定でデフォルト値に戻す)
	 * @return bool
	 *
	 */
	public function setTVbyID($id,$val=null){
		if( !empty($id) && array_key_exists($id,$this->tv) ){
			if( is_null($val) ){
				$this->tv[$id]['value'] = $this->tv[$id]['default'];
			}else{
				$this->tv[$id]['value'] = $val;
			}
			return true;
		}
		$this->logWarn('TV not exist:'.$name);
		return false;
	}

	/*
	 * すべてのTVを一括設定
	 *
	 * フォーマットは次の通り
	 *    [TVID]       ... TVID
	 *       [value]   ... TV値
	 *
	 * ※getAllTVs()のフォーマットに合わせてます
	 * ※配列にnameやdefaultが含まれていても無視します
	 * ※valueにnullを設定するとデフォルト値を採用
	 *
	 * @param $tv TV設定
	 * @return bool
	 *
	 */
	public function setAllTVs($tv){
		if( !is_array($tv) ){ return false; }
		foreach( $this->tv as $k => $v){
			if( isset($tv[$k]) ){
				$this->setTVbyID($k,$tv[$k]['value']);
			}
		}
		return true;
	}

	/*
	 * テンプレートの指定
	 *
	 * テンプレートを名前で指定する。
	 *
	 * ※tidに合わせて$this->tvを変更
	 * ※無効なテンプレートIDの場合、tidは0になる
	 *
	 * @param $name テンプレート名
	 * @return bool
	 *
	 */
	public function setTemplate($name){
		$rs  = $this->modx->db->select('id','[+prefix+]site_templates',"templatename= '" . $this->modx->db->escape($name) . "'");
		if( $row = $this->modx->db->getRow($rs) ){
			return $this->setTemplatebyID($row['id']);
		}
		$this->logWarn('無効なテンプレート名を指定しています。');
		
		return false;
	}

	/*
	 * テンプレートの指定(idベース)
	 *
	 * ※tidに合わせて$this->tvを変更
	 * ※無効なテンプレートIDの場合、tidは0になる
	 *
	 * @param $tid テンプレートID
	 * @return bool
	 *
	 */
	public function setTemplatebyID($tid){
		if( !$this->isInt($tid,0) ){
			return false;
		}
		if( $tid != 0 ){
			$rs  = $this->modx->db->select('id','[+prefix+]site_templates',"id= $tid");
			if( !($row = $this->modx->db->getRow($rs)) ){
				$this->logWarn('無効なテンプレートIDを指定しています。');
				$tid = 0;
			}
		}
		$this->content['template'] = $tid;
		//tv読み直し
		$this->tv = array();
		if( $this->documentExist($this->content['id']) ){
			//tv読み込み(値付)
			$sql = <<< SQL_QUERY
SELECT tv.id
      ,tv.name
      ,IFNULL(tvc.value,tv.default_text) AS value
      ,tv.default_text
FROM [+prefix+]site_tmplvars AS tv
  LEFT JOIN [+prefix+]site_tmplvar_templates AS tvt
    ON tvt.tmplvarid = tv.id
  LEFT JOIN [+prefix+]site_tmplvar_contentvalues AS tvc
    ON tvc.tmplvarid = tv.id AND tvc.contentid = {$this->content['id']}
WHERE tvt.templateid = {$this->content['template']}

SQL_QUERY;

			$sql = str_replace('[+prefix+]',$this->modx->db->config['table_prefix'],$sql);
			$rs  = $this->modx->db->query($sql);
			while( $row = $this->modx->db->getRow($rs) ){
				$this->tv[$row['id']]['name']    = $row['name'];
				$this->tv[$row['id']]['value']   = $row['value'];
				$this->tv[$row['id']]['default'] = $row['default_text'];
			}
		}else{
			//tv読み込み(値無)
			$sql = <<< SQL_QUERY
SELECT tv.id
	  ,tv.name
	  ,tv.default_text
FROM [+prefix+]site_tmplvars AS tv
  LEFT JOIN [+prefix+]site_tmplvar_templates AS tvt 
    ON tvt.tmplvarid = tv.id
  LEFT JOIN [+prefix+]site_templates AS st
	ON st.id = tvt.templateid
WHERE st.id = {$this->content['template']}
SQL_QUERY;

			$sql = str_replace('[+prefix+]',$this->modx->db->config['table_prefix'],$sql);
			$rs  = $this->modx->db->query($sql);
			while( $row = $this->modx->db->getRow($rs) ){
				$this->tv[$row['id']]['name']    = $row['name'];
				$this->tv[$row['id']]['value']   = $row['default_text'];
				$this->tv[$row['id']]['default'] = $row['default_text'];
			}
		}	
		return true;
	}

	/*
	 * リソースの存在確認
	 *
	 * 実際にリソースがあるか確認。
	 *
	 * @param $id リソースID
	 * @return bool  
	 *
	 */
	public function documentExist($id=''){
		if( empty($id) && isset($this->content['id']) ){
			$id=$this->content['id'];
		}
		if( !$this->isInt($id,1) ){
			return false;
		}
		$rs  = $this->modx->db->select('id','[+prefix+]site_content',"id = $id");
		if( $row = $this->modx->db->getRow($rs) ){
			return true;
		}
		return false;
	}

	/*
	 * load resource
	 *
	 * @param $id Resource id
	 * @return bool  
	 *
	 */
	public function load($id){
		$this->content = $this->content_lists;
		$this->tv = array();
		if( !$this->isInt($id,1) ){
			$this->logerr('リソースIDの指定が不正です。');
			return false;
		}else{
			$rs  = $this->modx->db->select('*','[+prefix+]site_content','id='.$id);
			$row = $this->modx->db->getRow($rs);
			if( empty($row) ){
				$this->logerr('リソースの読み込みに失敗しました。');
				return false;
			}
			$this->content = $row;
			//tv読み込み
			$this->setTemplatebyID($this->content['template']);			
		}
		return true;
	}
  
	/*
	 * リソースの保存
	 *
	 * fieldを指定すれば特定のレコードだけ保存する
	 * (先頭に「tv.」をつけるとTVが対象)
	 *
	 * @param $fields     Save target fields(blank or * = all)
	 * @param $clearCache Clear cache
	 * @return int/bool   save id or false
	 *
	 */
	public function save($fields='*',$clearCache=true){
		$c  = array(); //新規/更新対象content
		$tv = array(); //新規/更新対象tv

		if( empty($fields) || $fields == '*' ){
			foreach( $this->content as $key => $val ){
				if( !is_null($this->content[$key]) ){
					$c[$key] = $val;
				}
			}
			$tv = $this->tv;
		}else{
			if( !is_array($fields) )
				$fields = explode(',',$fields);
			foreach( $fields as $val ){
				if( isset($this->content[$val]) && !is_null($this->content[$val]) ){
					$c[$val] = $this->content[$val];
				}else{
					$tmp = $this->getTVName($val);
					if( $tmp !== false ){
						$tmp = $this->getTVID($tmp);
						$tv[$tmp] = $this->tv[$tmp];
					}else{
						$this->logWarn('Fields not exist:'.$val);
					}

				}
			}
		}

		//idは途中エラー時はfalseに変化
		if( $this->isInt($this->content['id'],1) ){
			$id = $this->content['id'];
			if( !$this->documentExist($id) ){
				$this->logerr('存在しないリソースIDを指定しています:'.$id);
				return false;
			}
		}else{
			$id = 0; //新規
		}

		// 日付調整
		foreach( $this->content_type_date as $val ){
			if( isset($c[$val]) && $c[$val] == 'now()' )
				$c[$val] = time();
		}

		//親リソース調整
		if( isset($c['parent']) ){ //nullの時に無視したいのであえてisset()を利用、同じような理由のif文が複数有
			if( !$this->isInt($c['parent'],0) ){
				$c['parent'] = 0;
			}
		}
		
		//メニューインデックス調整
		if( isset($c['menuindex']) ){
			if( $c['menuindex'] == 'auto' ){
				//自動採番
				if( $id != 0 && !array_key_exists('parent',$c) ){
					$rs = $this->modx->db->select('parent','[+prefix+]site_content',"id=$id");
					if( $row = $this->modx->db->getRow($rs) ){
						$pid = $row['parent'];
					}
				}elseif( isset($c['parent']) && !empty($c['parent']) ){
					$pid = $c['parent'];
				}else{
					$pid = 0;
				}
				$rs = $this->modx->db->select('(max(menuindex) + 1) AS menuindex','[+prefix+]site_content',"parent=$pid");
				if( ($row = $this->modx->db->getRow($rs)) && !empty($row['menuindex']) ){
					$c['menuindex'] = $row['menuindex'];
				}else{
					$c['menuindex'] = 0;
				}
			}elseif( !$this->isInt($c['menuindex'],0) ){
				$c['menuindex'] = 0;
			}
		}

		//content登録
		unset($c['id']);
		if( !empty($c) ){
			$c = $this->modx->db->escape($c);

			if( $id != 0 ){
				//update
				if( !$this->modx->db->update($c,'[+prefix+]site_content','id='.$id) )
					$id = false;
			}else{
				//insert
				$id = $this->modx->db->insert($c,'[+prefix+]site_content');
			}
		}
		
		//TVの登録
		if( $id === false ){
			$this->logerr('contentの保存に失敗しているため、tvの保存は行いません。');
		}elseif( $id == 0 ){
			$this->logerr('新規リソースの場合は最初にリソースを保存する必要があります。');
			$id = false;
		}else{
			$errflag = false;

			$tmp='';
			foreach( $tv as $k => $v ){
				if( $v['value'] === $v['default'] ){
					//デフォルト時は削除
					if( $this->isInt($k,1) ){
						$this->modx->db->delete('[+prefix+]site_tmplvar_contentvalues',
												"tmplvarid = $k AND contentid = $id");
					}
				}else{
					$rs  = $this->modx->db->select('id','[+prefix+]site_tmplvar_contentvalues',"tmplvarid = $k AND contentid = $id");
					if( $row = $this->modx->db->getRow($rs) ){
						$rs = $this->modx->db->update(array( 'value' => $this->modx->db->escape($v['value']) ),
													  '[+prefix+]site_tmplvar_contentvalues',
													  "tmplvarid = $k AND contentid = $id");
						if( !$rs ){
							$errflag = true;
						}
					}else{
						$rs = $this->modx->db->insert(array( 'tmplvarid' => $k ,
															 'contentid' => $id ,
															 'value' => $this->modx->db->escape($v['value'])
						                                   ),
													  '[+prefix+]site_tmplvar_contentvalues');
						if( !$rs ){
							$errflag = true;
						}
					}
				}
			}
		}
		if( $errflag ){
			$id = false;
		}
		
		if( $id !== false && $clearCache )
			$this->modx->clearCache();
            
		return $id;
	}

	/*
	 * delete resource
	 *
	 * @param $clearCache Clear cache
	 * @return bool   
	 *
	 */
	public function delete($clearCache=true){
		if( !$this->isInt($this->content['id'],1) )
			return false;

		$this->content['deleted'] = 1;
		$this->content['deletedon'] = 'now()';
		//$this->content['deletedby'] = 1;
		return $this->save('deleted,deletedon',$clearCache);
	}

	/*
	 * undelete resource
	 *
	 * @param $clearCache Clear cache
	 * @return bool   
	 *
	 */
	public function undelete($clearCache=true){
		if( !$this->isInt($this->content['id'],1) )
			return false;

		$this->content['deleted'] = 0;
		$this->content['deletedon'] = '';
		//$this->content['deletedby'] = 1;
		return $this->save('deleted,deletedon',$clearCache);
	}

	/*
	 * リソースの完全削除
	 *
	 * DBから削除します。
	 * $this->content も初期化されます。
	 *
	 * @param $clearCache Clear cache
	 * @return bool   
	 *
	 */
	public function erase($clearCache=true){
		if( $this->documentExist($this->content['id']) ){
			$id = $this->content['id'];
			//tvの削除 -> content削除
			foreach( $this->tv as $k => $v ){
				$this->modx->db->delete('[+prefix+]site_tmplvar_contentvalues',
										"tmplvarid = $k AND contentid = $id");
			}
			$rs = $this->modx->db->delete('[+prefix+]site_content',"id = $id");

			if( $rs ){
				$this->content = $this->content_lists;
				$this->tv = array();
			}
			
			if( $rs !== false && $clearCache ){
				$this->modx->clearCache();
			}
			return $rs;

		}
		$this->logErr('リソースが存在しません。');
		return false;
	}

	/*
	 * lastLog
	 *
	 * @param none
	 * @return string Log message
	 *
	 */
	public function lastLog(){
		return $this->lastLog;
	}

	/*
	 * logging / loginfo / logwarn / logerr
	 *
	 * @param level Log level
	 * @param msg Log massages
	 * @return bool   
	 *
	 */
	private function logging($level,$msg=''){
		$this->lastLog = $msg;
		if( $this->logLevel <= $level )
			$this->modx->logEvent(4,$level,$msg,'Document Object API');
	}
	
	private function loginfo($msg=''){
		$this->logging(self::LOG_INFO,$msg);   
	}
	
	private function logwarn($msg=''){
		$this->logging(self::LOG_WARN,$msg);   
	}
	
	private function logerr($msg=''){
		$this->logging(self::LOG_ERR,$msg);   
	}

	/*
	 * TV名を返す
	 *
	 * 先頭に「tv.」がある場合は削除される
	 * TV名ではない場合はfalseを返す
	 *
	 * @param $name 文字列
	 * @return string/false
	 *
	 */
	private function getTVName($name){
		$pos = strpos($name,'tv.');
		if( $pos === 0 ){
			$name = substr($name,3);
		}
		foreach( $this->tv as $k => $v ){
			if( $v['name'] == $name ){
				return $name;
			}
		}
		return false;
	}
									  
	//--- Sub method (This method might be good to be another share class.)
	/*
	 * Number check
	 *
	 * @param $param Input data
	 * @param $min   Minimum value
	 * @param $max   Maximum value
	 * @return bool
	 *
	 */
	private function isInt($param,$min=null,$max=null){
		if( !preg_match('/\A[0-9]+\z/', $param) ){
			return false;
		}
		if( !is_null($min) && preg_match('/\A[0-9]+\z/', $min) && $param < $min ){
			return false;
		}
		if( !is_null($max) && preg_match('/\A[0-9]+\z/', $max) && $param > $max ){
			return false;
		}
		return true;
	}  
}
