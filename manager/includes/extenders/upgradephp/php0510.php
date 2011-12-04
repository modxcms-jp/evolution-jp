<?php
/* url:		http://freshmeat.net/projects/upgradephp */

/**
 * Constants for future 64-bit integer support.
 *
 */
if (!defined("PHP_INT_SIZE")) { define("PHP_INT_SIZE", 4); }
if (!defined("PHP_INT_MAX")) { define("PHP_INT_MAX", 2147483647); }

/**
 * @flag bugfix
 * @see #33895
 *
 * Missing constants in 5.1, originally appeared in 4.0.
 */
if (!defined("M_SQRTPI")) { define("M_SQRTPI", 1.7724538509055); }
if (!defined("M_LNPI")) { define("M_LNPI", 1.1447298858494); }
if (!defined("M_EULER")) { define("M_EULER", 0.57721566490153); }
if (!defined("M_SQRT3")) { define("M_SQRT3", 1.7320508075689); }


/**
 * removes entities &lt; &gt; &amp; and eventually &quot; from HTML string
 *
 */
if (!function_exists("htmlspecialchars_decode")) {
   if (!defined("ENT_COMPAT")) { define("ENT_COMPAT", 2); }
   if (!defined("ENT_QUOTES")) { define("ENT_QUOTES", 3); }
   if (!defined("ENT_NOQUOTES")) { define("ENT_NOQUOTES", 0); }
   function htmlspecialchars_decode($string, $quotes=2) {
      $d = $quotes & ENT_COMPAT;
      $s = $quotes & ENT_QUOTES;
      return str_replace(
         array("&lt;", "&gt;", ($s ? "&quot;" : "&.-;"), ($d ? "&#039;" : "&.-;"), "&amp;"),
         array("<",    ">",    "'",                      "\"",                     "&"),
         $string
      );
   }
}

/**
 * @flag needs5
 *
 * Checks for existence of object property, should return TRUE even for NULL values.
 *
 * @compat
 *    no test for edge cases
 */
if (!function_exists("property_exists")) {
   function property_exists($obj, $propname) {
      if (is_object($obj)) {
         $props = array_keys(get_object_vars($obj));
      }
      elseif (class_exists($obj)) {
         $props = array_keys(get_class_vars($obj));
      }
      return !empty($props) and in_array($propname, $props);
   }
}

/**
 * halt execution, until given timestamp
 *
 */
if (!function_exists("time_sleep_until")) {
   function time_sleep_until($t) {
      $delay = $t - time();
      if ($delay < 0) {
         trigger_error("time_sleep_until: timestamp in the past", E_USER_WARNING);
         return false;
      }
      else {
         sleep((int)$delay);
         #usleep(($delay - floor($delay)) * 1000000);
         return true;
      }
   }
}

/**
 * @untested
 *
 * Writes an array as CSV text line into opened filehandle.
 *
 */
if (!function_exists("fputcsv")) {
   function fputcsv($fp, $fields, $delim=",", $encl='"') {
      $line = "";
      foreach ((array)$fields as $str) {
         $line .= ($line ? $delim : "")
                . $encl
                . str_replace(array('\\', $encl), array('\\\\'. '\\'.$encl), $str)
                . $encl;
      }
      fwrite($fp, $line."\n");
   }
}

/**
 * @flag basic
 * @untested
 *
 * @compat
 *    only implements a few basic regular expression lookups
 *    no idea how to handle all of it
 */
if (!function_exists("strptime")) {
   function strptime($str, $format) {
      static $expand = array(
         "%D" => "%m/%d/%y",
         "%T" => "%H:%M:%S",
      );
      static $map_r = array(
          "%S"=>"tm_sec",
          "%M"=>"tm_min",
          "%H"=>"tm_hour",
          "%d"=>"tm_mday",
          "%m"=>"tm_mon",
          "%Y"=>"tm_year",
          "%y"=>"tm_year",
          "%W"=>"tm_wday",
          "%D"=>"tm_yday",
          "%u"=>"unparsed",
      );
      static $names = array(
         "Jan" => 1, "Feb" => 2, "Mar" => 3, "Apr" => 4, "May" => 5, "Jun" => 6,
         "Jul" => 7, "Aug" => 8, "Sep" => 9, "Oct" => 10, "Nov" => 11, "Dec" => 12,
         "Sun" => 0, "Mon" => 1, "Tue" => 2, "Wed" => 3, "Thu" => 4, "Fri" => 5, "Sat" => 6,
      );

      #-- transform $format into extraction regex
      $format = str_replace(array_keys($expand), array_values($expand), $format);
      $preg = preg_replace("/(%\w)/", "(\w+)", preg_quote($format));

      #-- record the positions of all STRFCMD-placeholders
      preg_match_all("/(%\w)/", $format, $positions);
      $positions = $positions[1];
      
      #-- get individual values
      if (preg_match("#$preg#", "$str", $extracted)) {

         #-- get values
         foreach ($positions as $pos=>$strfc) {
            $v = $extracted[$pos + 1];

            #-- add
            if ($n = $map_r[$strfc]) {
               $vals[$n] = ($v > 0) ? (int)$v : $v;
            }
            else {
               $vals["unparsed"] .= $v . " ";
            }
         }
         
         #-- fixup some entries
         $vals["tm_wday"] = $names[ substr($vals["tm_wday"], 0, 3) ];
         if ($vals["tm_year"] >= 1900) {
            $tm_year -= 1900;
         }
         elseif ($vals["tm_year"] > 0) {
            $vals["tm_year"] += 100;
         }
         if ($vals["tm_mon"]) {
            $vals["tm_mon"] -= 1;
         }
         else {
            $vals["tm_mon"] = $names[ substr($vals["tm_mon"], 0, 3) ] - 1;
         }
         
         #-- calculate wday
         // ... (mktime)
      }
      return isset($vals) ? $vals : false;
   }
}
