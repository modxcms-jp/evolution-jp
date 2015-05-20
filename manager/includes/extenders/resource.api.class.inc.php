<?php
/*
 * API for Resource(Document)
 *
 */

class RESOURCE_API
{

  //private $id='';             //Resource ID
  private $content = array(); // site content
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
            'menuindex' => null ,
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

  private $content_type_date = array('pub_date','unpub_date','createdon','editedon','deletedon');
    
  /*
   * __construct
   *
   * @param $id   Resource ID(blank=New resource)
   * @return none
   *
   */
	public function __construct($id='')
  {
    global $modx;

    $this->modx = &$modx;

    if( $this->isInt($id,1) )
    {
      //Load content
      //$this->id=$id;
      $rs  = $this->modx->db->select('*','[+prefix+]site_content','id='.$id);
      $row = $this->modx->db->getRow($rs);
      if( !empty($row) )
        $this->content = $row;
      else
        $this->content = $this->content_lists;
    }
    else
      $this->content = $this->content_lists;
  }

  /*
   * getField
   *
   * @param $field Resource column name
   * @return string
   *
   */
	public function getField($field='content')
  {
    if( !empty($field) && array_key_exists($field,$this->content) )
      return $this->content[$field];
    else
      return false;
  }

  /*
   * setField
   *
   * @param $field Resource column name
   * @param $val   new value
   * @return bool
   *
   */
	public function setField($field='content',$val='')
  {
    if( array_key_exists($field,$this->content_lists) )
      $this->content[$field] = $val;
    else
      return false;
  }

  /*
   * save resource
   *
   * @param $fields     Save target fields(blank or * = all)
   * @param $clearCache Clear cache
   * @return int/bool   save id or false
   *
   */
	public function save($fields='*',$clearCache=true)
  {
    $c = array(); //insert/update content
    if( empty($fields) || $fields == '*' )
    {
      foreach( $this->content as $key => $val )
      {
        if( !is_null($this->content[$key]) )
          $c[$key] = $val;
      }
    }
    else
    {
      if( !is_array($fields) )
        $fields = explode(',',$fields);
      foreach( $fields as $val )
      {
        if( isset($this->content[$val]) && !is_null($this->content[$val]) )
          $c[$val] = $this->content[$val];
        else
          $this->modx->logEvent(0,1,'Fields not exsist:'.$val,'Resource API');
      }
    }

    // date control
    foreach( $this->content_type_date as $val )
    {
      if( isset($c[$val]) && $c[$val] == 'now()' )
        $c[$val] = time();
    }

    unset($c['id']);
    $c = $this->modx->db->escape($c);

    if( $this->isInt($this->content['id'],1) )
    {
      //update
      $id = $this->content['id'];
      if( !$this->modx->db->update($c,'[+prefix+]site_content','id='.$id) )
        $id = false;
    }
    else
    {
      //insert
      $this->modx->logEvent(0,1,'data='.print_r($c,true),'[debug]Resource API');
      $id = $this->modx->db->insert($c,'[+prefix+]site_content');
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
	public function delete($clearCache=true)
  {
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
	public function undelete($clearCache=true)
  {
    if( !$this->isInt($this->content['id'],1) )
      return false;

    $this->content['deleted'] = 0;
    $this->content['deletedon'] = '';
    //$this->content['deletedby'] = 1;
    return $this->save('deleted,deletedon',$clearCache);
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
  private function isInt($param,$min=null,$max=null)
  {
    if( !preg_match('/\A[0-9]+\z/', $param) )
    {
      return false;
    }
    if( !is_null($min) && preg_match('/\A[0-9]+\z/', $min) && $param < $min )
    {
      return false;
    }
    if( !is_null($max) && preg_match('/\A[0-9]+\z/', $max) && $param > $max )
    {
      return false;
    }
    return true;
  }
  
}
