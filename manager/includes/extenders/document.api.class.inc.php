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

	public static $modx=null; //MODXオブジェクトを指定しないとこのクラスは動作しません

	//private $id='';                  // Resource ID
	private $content = array();        // Site content
	private $tv      = array();        // Template Value
	private $logLevel = self::LOG_ERR; // Output log level
	private $lastLog = '';             // Last log message

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
		if( self::isInt($level,1) )
			$this->logLevel = $level;

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
		$rs  = self::$modx->db->select('id','[+prefix+]site_templates',"templatename= '" . self::$modx->db->escape($name) . "'");
		if( $row = self::$modx->db->getRow($rs) ){
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
		if( !self::isInt($tid,0) ){
			return false;
		}
		if( $tid != 0 ){
			$rs  = self::$modx->db->select('id','[+prefix+]site_templates',"id= $tid");
			if( !($row = self::$modx->db->getRow($rs)) ){
				$this->logWarn('無効なテンプレートIDを指定しています。');
				$tid = 0;
			}
		}
		$this->content['template'] = $tid;
		//tv読み直し
		$this->tv = array();
		if( self::documentExist($this->content['id']) ){
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

			$sql = str_replace('[+prefix+]',self::$modx->db->config['table_prefix'],$sql);
			$rs  = self::$modx->db->query($sql);
			while( $row = self::$modx->db->getRow($rs) ){
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

			$sql = str_replace('[+prefix+]',self::$modx->db->config['table_prefix'],$sql);
			$rs  = self::$modx->db->query($sql);
			while( $row = self::$modx->db->getRow($rs) ){
				$this->tv[$row['id']]['name']    = $row['name'];
				$this->tv[$row['id']]['value']   = $row['default_text'];
				$this->tv[$row['id']]['default'] = $row['default_text'];
			}
		}	
		return true;
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
		if( !self::isInt($id,1) ){
			$this->logerr('リソースIDの指定が不正です。');
			return false;
		}else{
			$rs  = self::$modx->db->select('*','[+prefix+]site_content','id='.$id);
			$row = self::$modx->db->getRow($rs);
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
		if( self::isInt($this->content['id'],1) ){
			$id = $this->content['id'];
			if( !self::documentExist($id) ){
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
			if( !self::isInt($c['parent'],0) ){
				$c['parent'] = 0;
			}
		}
		
		//メニューインデックス調整
		if( isset($c['menuindex']) ){
			if( $c['menuindex'] == 'auto' ){
				//自動採番
				if( $id != 0 && !array_key_exists('parent',$c) ){
					$rs = self::$modx->db->select('parent','[+prefix+]site_content',"id=$id");
					if( $row = self::$modx->db->getRow($rs) ){
						$pid = $row['parent'];
					}
				}elseif( isset($c['parent']) && !empty($c['parent']) ){
					$pid = $c['parent'];
				}else{
					$pid = 0;
				}
				$rs = self::$modx->db->select('(max(menuindex) + 1) AS menuindex','[+prefix+]site_content',"parent=$pid");
				if( ($row = self::$modx->db->getRow($rs)) && !empty($row['menuindex']) ){
					$c['menuindex'] = $row['menuindex'];
				}else{
					$c['menuindex'] = 0;
				}
			}elseif( !self::isInt($c['menuindex'],0) ){
				$c['menuindex'] = 0;
			}
		}

		//content登録
		unset($c['id']);
		if( !empty($c) ){
			$c = self::$modx->db->escape($c);

			if( $id != 0 ){
				//update
				if( !self::$modx->db->update($c,'[+prefix+]site_content','id='.$id) )
					$id = false;
			}else{
				//insert
				$id = self::$modx->db->insert($c,'[+prefix+]site_content');
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
					if( self::isInt($k,1) ){
						self::$modx->db->delete('[+prefix+]site_tmplvar_contentvalues',
												"tmplvarid = $k AND contentid = $id");
					}
				}else{
					$rs  = self::$modx->db->select('id','[+prefix+]site_tmplvar_contentvalues',"tmplvarid = $k AND contentid = $id");
					if( $row = self::$modx->db->getRow($rs) ){
						$rs = self::$modx->db->update(array( 'value' => self::$modx->db->escape($v['value']) ),
													  '[+prefix+]site_tmplvar_contentvalues',
													  "tmplvarid = $k AND contentid = $id");
						if( !$rs ){
							$errflag = true;
						}
					}else{
						$rs = self::$modx->db->insert(array( 'tmplvarid' => $k ,
															 'contentid' => $id ,
															 'value' => self::$modx->db->escape($v['value'])
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
			self::$modx->clearCache();
            
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
		if( !self::isInt($this->content['id'],1) )
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
		if( !self::isInt($this->content['id'],1) )
			return false;

		$this->content['deleted'] = 0;
		$this->content['deletedon'] = '';
		//$this->content['deletedby'] = 1;
		return $this->save('deleted,deletedon',$clearCache);
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

	//--- 以下はstaticメソッド
	/*
	 * リソースの存在確認
	 *
	 * 実際にリソースがあるか確認。
	 *
	 * @param $id リソースID
	 * @return bool  
	 *
	 */
	public static function documentExist($id){
		if( !self::isInt($id,1) ){
			return false;
		}
		$rs  = self::$modx->db->select('id','[+prefix+]site_content',"id = $id");
		if( $row = self::$modx->db->getRow($rs) ){
			return true;
		}
		return false;
	}

	/*
	 * リソースの公開/非公開
	 *
	 * 対象リソースを公開/非公開にする。
	 * onPubを省略したら現在の状態を返す。
	 *
	 * @param $id リソースID
	 * @param $onPub 1…公開/0…非公開(true/falseでも可)
	 * @param $clearCache キャッシュクリアの有無
	 * @return 1/0/bool
	 *
	 */
	public static function chPublish($id,$onPub=null,$clearCache=true){
		if( !self::isInt($id,1) )
			return false;

		if( is_null($onPub) ){
			//値の参照
			$rs  = self::$modx->db->select('id,published','[+prefix+]site_content',"id = $id");
			if( $row = self::$modx->db->getRow($rs) ){
				return $row['published'];
			}
			return false;
		}

		//値の更新
		$onPub = self::bool2Int($onPub);
		if( self::documentExist($id) ){
			if( self::$modx->db->update( array('published' =>$onPub),'[+prefix+]site_content',"id = $id") ){
				if( $clearCache )
					self::$modx->clearCache();
				return true;
			}
		}
		return false;
	}

	/*
	 * リソースの完全削除
	 *
	 * DBからリソースを削除します。
	 * 削除フラグが落ちていると削除しません。
	 *
	 * @param $id リソースID
	 * @param $force trueの場合、強制削除(削除フラグ無視)(デフォルト:false)
	 * @param $recursive trueの場合、子リソースも削除(※未実装機能)
	 * @param $clearCache Clear cache
	 * @return bool
	 *
	 */
	public static function erase($id,$force=false,$recursive=false,$clearCache=true){
		if( self::documentExist($id) ){
			if( $force == false ){
				$rs  = self::$modx->db->select('id,deleted','[+prefix+]site_content',"id = $id");
				if( ($row = self::$modx->db->getRow($rs)) && $row['deleted'] != 1 ){
					return false;
				}
			}

			//tvの削除 -> content削除
			self::$modx->db->delete('[+prefix+]site_tmplvar_contentvalues',"contentid = $id");
			$rs = self::$modx->db->delete('[+prefix+]site_content',"id = $id");

			//子リソースの削除(※未実装)
			//...

			if( $rs !== false && $clearCache ){
				self::$modx->clearCache();
			}
			return $rs;
		}
		return false;
	}

	//--- 以下はプライベートメソッド
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
			self::$modx->logEvent(4,$level,$msg,'Document Object API');
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
	private static function isInt($param,$min=null,$max=null){
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

	/*
	 * bool型をIntに変換
	 *
	 * DBに登録できるようboolを0/1に変換。
	 * $paramに1/0が渡ってきた場合はそのまま返す。
	 * 認識できない$paramはすべて 0 とする。
	 *
	 * @param $param bool or 0/1
	 * @return 0/1
	 *
	 */
	private static function bool2Int($param){
		if( $param === true || $param == 1 ){
			return 1;
		}
		return 0;
	}

}
