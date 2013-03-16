<?php
class udperms {
    var $user;
    var $document;
    var $role;
    var $duplicateDoc = false;

    function checkPermissions() {

        global $udperms_allowroot;
        global $modx;

        $user     = $this->user;
        $document = $this->document;
        $role     = $this->role;

        if($modx->hasPermission('save_role'))             return true; // administrator - grant all document permissions
        elseif($document == 0 && $udperms_allowroot == 1) return true;
        elseif(empty($modx->config['use_udperms']))       return true; // permissions aren't in use
        
        if (strpos($document, ',') !== false) {
            $document = substr($document, 0, strpos($document, ','));
        }

        $parent = $modx->db->getValue($modx->db->select('parent', '[+prefix+]site_content', "id='{$document}'"));
        if ($this->duplicateDoc == true && $parent == 0 && $udperms_allowroot == 0) {
            return false; // deny duplicate document at root if Allow Root is No
        }

        // get document groups for current user
        if (isset($_SESSION['mgrDocgroups']) && !empty($_SESSION['mgrDocgroups'])) {
            $docgrp = implode(" || dg.document_group = ", $_SESSION['mgrDocgroups']);
            $where_docgrp = "(dg.document_group = {$docgrp} || sc.privatemgr = 0)";
        }
        else $where_docgrp = 'sc.privatemgr = 0';
        
		$field = 'DISTINCT sc.id';
		$from   = '[+prefix+]site_content sc';
		$from  .= ' LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
		$from  .= ' LEFT JOIN [+prefix+]documentgroup_names dgn ON dgn.id = dg.document_group';
		$where = "sc.id = '{$document}' AND {$where_docgrp}";

		$rs = $modx->db->select($field,$from,$where);
		$limit = $modx->db->getRecordCount($rs);
		
		if ($limit == 1) $permissionsok = true;
		else             $permissionsok = false;
		
		return $permissionsok;
    }
}
