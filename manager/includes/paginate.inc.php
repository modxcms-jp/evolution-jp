<?php
// =====================================================
// FILE: Paging.php
//
// =====================================================
// Description: This class handles the paging from a query to be print 
//              to the browser. You can customize it to your needs.
//
// This is distribute as is. Free of use and free to do anything you want.
//
// PLEASE REPORT ANY BUG TO ME BY EMAIL :)
//
// =========================
// Programmer:	  Pierre-Yves Lemaire
//											pylem_2000@yahoo.ca
// =========================
// Date:			2001-03-25
// Version: 2.0
//
// Modif: 
// Version 1.1 (2001-04-09) Remove 3 lines in getNumberOfPage() that were forgot after debugging
// Version 1.1 (2001-04-09) Modification to the exemple
// Version 1.1 (2001-04-10) Added more argv to the previous and next link. ( by: peliter@mail.peliter.com )

// Version 2.0 (2001-11-22) Complete re-write of the script
// Summary: The class will be make it easier to play with results...
// * The class now only returns 2 arrays. All HTML, except href, tag were remove.
// * Function printPaging() broken in two: getPagingArray() and getPagingRowArray()
// * Function openTable() and closeTable() removed.
// =====================================================
class Paging {
  
  var $script_name;
  var $int_num_result;  // Number of result to show per page (decided by user)
  var $int_nbr_row;     // Total number of items (SQL count from db)
  var $int_cur_position;// Current position in recordset
  var $str_ext_argv;    // Extra argv of query string
  
  function __construct( $int_nbr_row, $int_cur_position, $int_num_result, $str_ext_argv = "" ){
    $this->script_name = $_SERVER['SCRIPT_NAME'];
    $this->int_nbr_row = $int_nbr_row;
    $this->int_num_result = $int_num_result;
    $this->int_cur_position = $int_cur_position;
    $this->str_ext_argv = urldecode( $str_ext_argv );
  } // End constructor

  // This function returns the total number of page to display.
  function getNumberOfPage(){
    return $this->int_nbr_row / $this->int_num_result;
  }

  // This function returns the current page number.
  function getCurrentPage(){
    $int_cur_page = ( $this->int_cur_position * $this->getNumberOfPage() ) / $this->int_nbr_row;
    return number_format( $int_cur_page, 0 );
  }
  
  function getPagingArray(){

    $paging['lower'] = ( $this->int_cur_position + 1 );

    if( $this->int_cur_position + $this->int_num_result >= $this->int_nbr_row ){
      $paging['upper'] = $this->int_nbr_row;
    }else{
      $paging['upper'] = ( $this->int_cur_position + $this->int_num_result );
    }
    
    $paging['total'] = $this->int_nbr_row;

    if ( $this->int_cur_position != 0 ){
      $paging['first_link'] = '<a href="' . $this->script_name . '?int_cur_position=0' . $this->str_ext_argv . '">';
      $paging['previous_link'] = '<a href="' . $this->script_name . '?int_cur_position=' . ($this->int_cur_position - $this->int_num_result) . $this->str_ext_argv . '">';
    }
    
    if( ( $this->int_nbr_row - $this->int_cur_position ) > $this->int_num_result ){
      $int_new_position = $this->int_cur_position + $this->int_num_result;
      $paging['last_link'] = '<a href="' . $this->script_name . '?int_cur_position=' . $this->int_nbr_row.$this->str_ext_argv . '">';
      $paging['next_link'] = '<a href="' . $this->script_name . '?int_cur_position='. $int_new_position . $this->str_ext_argv . '">';
    }
    return $paging;
  }

  // This function returns an array of string (href link with the page number)
  function getPagingRowArray(){

    for( $i=0; $i<$this->getNumberOfPage(); $i++ ){
      // if current page, do not make a link
      if( $i == $this->getCurrentPage() ){
        $array_all_page[$i] = '<b>'. ($i+1) .'</b>&nbsp;';
      }else{
        $int_new_position = ( $i * $this->int_num_result );
        $array_all_page[$i] = '<a href="' . $this->script_name . '?int_cur_position=' . $int_new_position . $this->str_ext_argv . '">'. ($i+1) .'</a>&nbsp;';
      }
    }
    return $array_all_page;
  }
}