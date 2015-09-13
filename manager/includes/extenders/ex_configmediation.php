<?php
/*
 * Config mediation class
 * (This class mediate config)
 *
 */

class CONFIG_MEDIATION
{
  public $hasOutput = false;

  private $modx;
  private $params;
  private $output = '';

  /*
   * __construct
   *
   * @param $modx MODX Object(default:global $modx)
   * @return none
   *
   */
  public function __construct(&$orgmodx=null)
  {
    global $modx;
    if( is_null($orgmodx) )
      $this->modx = $modx;
    else
      $this->modx = $orgmodx;
  }

  /*
   * set Paramaters
   *
   * @param $params Paramaters
   * @return none
   *
   */
  public function setParams($params)
  {
    $this->params = $params;
  }
  
  /*
   * set Paramater
   *
   * @param $key Key name
   * @param $val value(default: null)
   * @return bool
   *
   */
  public function setParam($key,$val=null)
  {
    if( isset($this->params[$key]) ){
      $this->params[$key] = $val;
      return true;
    }
    return false;
  }

  /*
   * get Paramater keys
   *
   * @param none
   * @return all param keys
   *
   */
  public function getParamKeys()
  {
    $out = array();
    if( is_array($this->params) )
    {
      foreach( $this->params as $key => $val)
      {
        $out[] = $key;
      }
    }
    return $out;
  }

  /*
   * get Paramaters
   *
   * @param none
   * @return all params
   *
   */
  public function getParams()
  {
    return $this->params;
  }

  /*
   * get Paramater
   *
   * @param $key Key name
   * @return param value oe null
   *
   */
  public function getParam($key)
  {
    if( isset($this->params[$key]) ){
      return $this->params[$key];
    }
    return null;
  }

  /*
   * add Output
   *
   * @param $data Output data
   * @return none
   *
   */
  public function addOutput($data)
  {
    $this->hasOutput = true;
    $this->output .= $data;
  }

  /*
   * Show output
   *
   * @param none
   * @return output data
   *
   */
  public function showOutput()
  {
    return $this->output;
  }

}
