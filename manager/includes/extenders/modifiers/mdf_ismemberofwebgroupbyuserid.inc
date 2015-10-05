<?php
    
    return isMemberOfWebGroupByUserId($userid,$groupNames);
    
    function isMemberOfWebGroupByUserId($userid=0,$groupNames=array()) {
        global $modx;
        
        // if $groupNames is not an array return false
        if(!is_array($groupNames)) return false;
        
        // if the user id is a negative number make it positive
        if (intval($userid) < 0) { $userid = -($userid); }
        
        // Creates an array with all webgroups the user id is in
        if (isset($modx->filter->cache['mo'][$userid])) $grpNames = $modx->filter->cache['mo'][$userid];
        else
        {
            $from = sprintf("[+prefix+]webgroup_names wgn INNER JOIN [+prefix+]web_groups wg ON wg.webgroup=wgn.id AND wg.webuser='%s'",$userid);
            $rs = $modx->db->select('wgn.name',$from);
            $modx->filter->cache['mo'][$userid] = $grpNames = $modx->db->getColumn('name',$rs);
        }
        
        // Check if a supplied group matches a webgroup from the array we just created
        foreach($groupNames as $k=>$v)
        {
            if(in_array(trim($v),$grpNames)) return true;
        }
        
        // If we get here the above logic did not find a match, so return false
        return false;
    }
