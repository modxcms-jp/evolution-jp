<?php
function getActionButtons($id)
{
    switch (manager()->action) {
        case '4':
        case '72':
            if (hasPermission('new_document')) {
                $ph['saveButton'] = ab_save();
            }
            break;
        case '27':
            if (hasPermission('save_document')) {
                $ph['saveButton'] = ab_save();
            }
            break;
        case '132':
        case '131':
            $ph['saveButton'] = ab_save();
            break;
        default:
            $ph['saveButton'] = '';
    }

    $ph['moveButton'] = '';
    $ph['duplicateButton'] = '';
    $ph['deleteButton'] = '';
    if (evo()->doc->mode === 'draft') {
        if (evo()->revision->hasDraft || evo()->revision->hasStandby) {
            $ph['deleteButton'] = ab_delete_draft();
        }
    } elseif ($id != config('site_start')) {
        if (manager()->action == 27 && evo()->doc->canSaveDoc()) {
            if (hasPermission('move_document')) {
                $ph['moveButton'] = ab_move();
            }
            if (evo()->doc->canCreateDoc()) {
                $ph['duplicateButton'] = ab_duplicate();
            }
            if (evo()->doc->canDeleteDoc()) {
                if (doc('deleted') == 0) {
                    $ph['deleteButton'] = ab_delete();
                } else {
                    $ph['deleteButton'] = ab_undelete();
                }
            }
        }
    }

    if (manager()->action == 27) {
        if (evo()->revision->hasDraft || evo()->revision->hasStandby) {
            $ph['draftButton'] = ab_open_draft($id);
        } else {
            $ph['draftButton'] = ab_create_draft($id);
        }

    } else {
        $ph['draftButton'] = '';
    }

    $ph['previewButton'] = ab_preview($id);
    $ph['cancelButton'] = ab_cancel($id);

    return preg_replace('@\[\+[^]]+\+]@', '', parseText(file_get_tpl('action_buttons.tpl'), $ph));
}

function ab_preview($id = 0)
{
    $tpl = '<li id="preview"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('preview');
    return parseText($tpl, $ph);
}

function ab_save()
{
    $tpl = '<li id="save" class="primary mutate"><a href="#">[+label+]</a>[+select+]</li>';
    $ph['label'] = lang('update');

    // 下書きの画面だけの選択肢（採用する・採用日時を指定する）
    $extra = [];
    if (evo()->doc->mode === 'draft' && hasPermission('publish_document')) {
        if (evo()->revision->hasStandby) {
            $extra['save_standby'] = '下書採用日時を再指定';
        } else {
            $extra['save_draft'] = '下書きを採用';
        }
    }
    $withNew = evo()->doc->mode !== 'draft' && hasPermission('new_document') && hasPermission('save_document');
    $selected = EvoFormFlow::requestedAfterSave(['save_draft', 'save_standby'])
        ?: EvoFormFlow::documentAfterSaveDefault();

    $ph['select'] = sprintf(
        '<span class="and"> + </span><select id="stay" name="after_save">%s</select>',
        EvoFormFlow::afterSaveOptions($selected, $withNew, $extra)
    );

    return parseText($tpl, $ph);
}

function ab_open_draft($id)
{
    $tpl = '<li id="opendraft" class="opendraft mutate"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('open_draft');
    return parseText($tpl, $ph);
}

function ab_create_draft($id)
{
    if (!config('enable_draft')) {
        return false;
    }

    if (!hasPermission('edit_document')) {
        return false;
    }

    $tpl = '<li id="createdraft" class="mutate"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('create_draft');

    return parseText($tpl, $ph);
}

function ab_cancel($id)
{
    $tpl = '<li id="cancel" class="mutate"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('cancel');
    return parseText($tpl, $ph);
}

function ab_move()
{
    $tpl = '<li id="move" class="mutate"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('move');
    return parseText($tpl, $ph);
}

function ab_duplicate()
{
    $tpl = '<li id="duplicate"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('duplicate');
    return parseText($tpl, $ph);
}

function ab_delete()
{
    $tpl = '<li id="delete"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('delete');
    return parseText($tpl, $ph);
}

function ab_undelete()
{
    $tpl = '<li id="undelete"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('undelete_resource');
    return parseText($tpl, $ph);
}

function ab_delete_draft()
{
    $tpl = '<li id="deletedraft"><a href="#">[+label+]</a></li>';
    $ph['label'] = lang('delete_draft');
    return parseText($tpl, $ph);
}
