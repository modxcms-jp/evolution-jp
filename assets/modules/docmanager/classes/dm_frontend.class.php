<?php

class DocManagerFrontend
{
    var $dm = null;

    function __construct(&$dm)
    {
        $this->dm = &$dm;
        include(MODX_CORE_PATH . 'controls/datagrid.class.php');
    }

    function getViews()
    {
        $this->renderTemplates();
        $this->renderTemplateVars();
        $this->renderDocGroups();
        $this->renderSort();
        $this->renderMisc();
        $this->renderChangeAuthors();
        $this->renderDocumentSelect();
    }

    function renderTemplates()
    {
        $grd = new DataGrid(
            '',
            db()->select('id,templatename,description', '[+prefix+]site_templates', '', 'id ASC')
        );
        $grd->noRecordMsg = $this->dm->lang['DM_tpl_no_templates'];
        $grd->cssClass = "grid";
        $grd->columnHeaderClass = "gridHeader";
        $grd->itemClass = "gridItem";
        $grd->altItemClass = "gridAltItem";
        $grd->columns = " ," . $this->dm->lang['DM_tpl_column_id'] . "," . $this->dm->lang['DM_tpl_column_name'] . "," . $this->dm->lang['DM_tpl_column_description'];
        $grd->colTypes = "template:<input type='radio' name='id' value='[+id+]' />";
        $grd->colWidths = "5%,5%,40%,50%";
        $grd->fields = "template,id,templatename,description";

        $this->dm->ph['baseurl'] = MODX_BASE_URL;
        $this->dm->ph['templates.grid'] = $grd->render();
        $this->dm->ph['view.templates'] = $this->dm->parseTemplate('templates.tpl', $this->dm->ph);
    }

    function renderTemplateVars()
    {
        $grd = new DataGrid(
            '',
            db()->select('id,templatename,description', '[+prefix+]site_templates', '', 'id ASC')
        );
        $grd->noRecordMsg = $this->dm->lang['DM_tpl_no_templates'];
        $grd->cssClass = "grid";
        $grd->columnHeaderClass = "gridHeader";
        $grd->itemClass = "gridItem";
        $grd->altItemClass = "gridAltItem";
        $grd->columns = " ," . $this->dm->lang['DM_tpl_column_id'] . "," . $this->dm->lang['DM_tpl_column_name'] . "," . $this->dm->lang['DM_tpl_column_description'];
        $grd->colTypes = 'template:<input name="tid" type="radio" value="[+id+]" onclick="loadTemplateVars(\'[+id+]\');" />';
        $grd->colWidths = "5%,5%,40%,50%";
        $grd->fields = "template,id,templatename,description";

        $this->dm->ph['templatevars.grid'] = $grd->render();
        $this->dm->ph['view.templatevars'] = $this->dm->parseTemplate('templatevars.tpl', $this->dm->ph);
    }

    function renderDocGroups()
    {
        $grd = new DataGrid(
            '',
            db()->select('id,name', '[+prefix+]documentgroup_names', '', 'id ASC')
        );
        $grd->noRecordMsg = $this->dm->lang['DM_doc_no_docs'];
        $grd->cssClass = "grid";
        $grd->columnHeaderClass = "gridHeader";
        $grd->itemClass = "gridItem";
        $grd->altItemClass = "gridAltItem";
        $grd->columns = " ," . $this->dm->lang['DM_doc_column_id'] . "," . $this->dm->lang['DM_doc_column_name'];
        $grd->colTypes = "template:<input type='radio' name='docgroupid' value='[+id+]' />";
        $grd->colWidths = "5%,5%,40%,50%";
        $grd->fields = "template,id,name";

        $this->dm->ph['documentgroups.grid'] = $grd->render();
        $this->dm->ph['view.documentgroups'] = $this->dm->parseTemplate('documentgroups.tpl', $this->dm->ph);
    }

    function renderDocumentSelect()
    {
        $this->dm->ph['view.documents'] = $this->dm->parseTemplate('documents.tpl', $this->dm->ph);
    }

    function renderSort()
    {
        $this->dm->ph['view.sort'] = $this->dm->parseTemplate('sort.tpl', $this->dm->ph);
    }

    function renderMisc()
    {
        $this->dm->ph['view.misc'] = $this->dm->parseTemplate('misc.tpl', $this->dm->ph);
    }

    function renderChangeAuthors()
    {
        $users = db()->select('id,username', evo()->getFullTableName('manager_users'));
        $userOptions = '';

        while ($row = db()->getRow($users)) {
            $userOptions .= '<option value="' . $row['id'] . '">' . $row['username'] . '</option>';
        }
        $this->dm->ph['changeauthors.options'] = $userOptions;
        $this->dm->ph['view.changeauthors'] = $this->dm->parseTemplate('changeauthors.tpl', $this->dm->ph);
    }
}
