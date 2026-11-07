<?php

class DocManagerFrontend
{
    var $dm = null;
    private $templateRows = null;

    function __construct(&$dm)
    {
        $this->dm = &$dm;
        include_once(MODX_CORE_PATH . 'controls/data_table.class.php');
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

    /**
     * 先頭にラジオ、続けて ID・名前（・説明）の列を持つ表を描く。
     * 名前・説明は既定でエスケープする（説明は要素の説明として b・strong だけ戻す）。
     */
    private function radioTable(array $rows, string $tableId, string $radioHtml, array $labels, string $emptyMessage, bool $hasDescription): string
    {
        $columns = [
            [
                'key' => 'id',
                'label' => '',
                'class' => 'is-shrink',
                'html' => true,
                'format' => function ($value) use ($radioHtml) {
                    $id = (int) $value;
                    return strtr($radioHtml, ['[+id+]' => $id]);
                },
            ],
            ['key' => 'id', 'label' => $labels['id'], 'class' => 'is-shrink'],
            ['key' => 'name', 'label' => $labels['name']],
        ];
        if ($hasDescription) {
            $columns[] = [
                'key' => 'description',
                'label' => $labels['description'],
                'html' => true,
                'format' => function ($value) {
                    return element_description_html((string) $value);
                },
            ];
        }
        $table = new DataTable($columns, [
            'id' => $tableId,
            'tableClass' => 'grid',
            'rowClasses' => ['gridItem', 'gridAltItem'],
            'emptyMessage' => $emptyMessage,
        ]);
        return $table->render($rows);
    }

    private function templateRows(): array
    {
        // テンプレートの表と TV の表で同じ行を使うので 1 回だけ取る
        if ($this->templateRows === null) {
            $this->templateRows = db()->getRows(
                db()->select('id,templatename AS name,description', '[+prefix+]site_templates', '', 'id ASC')
            ) ?: [];
        }
        return $this->templateRows;
    }

    private function templateLabels(): array
    {
        return [
            'id' => $this->dm->lang['DM_tpl_column_id'],
            'name' => $this->dm->lang['DM_tpl_column_name'],
            'description' => $this->dm->lang['DM_tpl_column_description'],
        ];
    }

    function renderTemplates()
    {
        // 「テンプレート無し」(id 0) は表の先頭の行にする
        $rows = array_merge(
            [['id' => 0, 'name' => $this->dm->lang['DM_tpl_blank_template'], 'description' => '']],
            $this->templateRows()
        );

        $this->dm->ph['baseurl'] = MODX_BASE_URL;
        $this->dm->ph['templates.grid'] = $this->radioTable(
            $rows,
            'dm-templates',
            '<input type="radio" name="id" value="[+id+]">',
            $this->templateLabels(),
            $this->dm->lang['DM_tpl_no_templates'],
            true
        );
        $this->dm->ph['view.templates'] = $this->dm->parseTemplate('templates.tpl', $this->dm->ph);
    }

    function renderTemplateVars()
    {
        $this->dm->ph['templatevars.grid'] = $this->radioTable(
            $this->templateRows(),
            'dm-templatevars',
            '<input type="radio" name="tid" value="[+id+]" data-template-id="[+id+]">',
            $this->templateLabels(),
            $this->dm->lang['DM_tpl_no_templates'],
            true
        );
        $this->dm->ph['view.templatevars'] = $this->dm->parseTemplate('templatevars.tpl', $this->dm->ph);
    }

    function renderDocGroups()
    {
        $rows = db()->getRows(
            db()->select('id,name', '[+prefix+]documentgroup_names', '', 'id ASC')
        ) ?: [];

        $this->dm->ph['documentgroups.grid'] = $this->radioTable(
            $rows,
            'dm-documentgroups',
            '<input type="radio" name="docgroupid" value="[+id+]">',
            [
                'id' => $this->dm->lang['DM_doc_column_id'],
                'name' => $this->dm->lang['DM_doc_column_name'],
            ],
            $this->dm->lang['DM_doc_no_docs'],
            false
        );
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
            $userOptions .= '<option value="' . (int) $row['id'] . '">' . hsc($row['username']) . '</option>';
        }
        $this->dm->ph['changeauthors.options'] = $userOptions;
        $this->dm->ph['view.changeauthors'] = $this->dm->parseTemplate('changeauthors.tpl', $this->dm->ph);
    }
}
