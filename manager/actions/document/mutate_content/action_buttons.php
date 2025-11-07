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
    $tpl = '<li id="preview"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = style('icons_preview_resource');
    $ph['alt'] = 'preview resource';
    $ph['label'] = lang('preview');
    return parseText($tpl, $ph);
}

function ab_save()
{
    $tpl = '<li id="save" class="primary mutate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a>[+select+]</li>';
    $ph['icon'] = style('icons_save');
    $ph['alt'] = 'icons_save';
    $ph['label'] = lang('update');

    $ph['select'] = '<span class="and"> + </span><select id="stay" name="stay">%s</select>';
    $saveAfter = anyv('stay', sessionv('saveAfter', 'close'));
    $selected = ['new' => '', 'stay' => '', 'close' => ''];
    if (hasPermission('new_document')
        && $saveAfter === 'new') {
        $selected['new'] = 'selected';
    } elseif ($saveAfter === 'stay') {
        $selected['stay'] = 'selected';
    } elseif ($saveAfter === 'close') {
        $selected['close'] = 'selected';
    } else {
        $selected['close'] = 'selected';
    }

    if (evo()->doc->mode !== 'draft' && hasPermission('new_document') && hasPermission('save_document')) {
        $option[] = sprintf('<option id="stay1" value="new" %s >%s</option>', $selected['new'], lang('stay_new'));
    }

    $option[] = sprintf('<option id="stay2" value="stay" %s >%s</option>', $selected['stay'], lang('stay'));
    if (evo()->doc->mode === 'draft' && hasPermission('publish_document')) {
        if (evo()->revision->hasStandby) {
            $option[] = sprintf('<option id="stay4" value="save_standby">%s</option>', '下書採用日時を再指定');
        } else {
            $option[] = sprintf('<option id="stay4" value="save_draft">%s</option>', '下書きを採用');
        }
    }
    $option[] = sprintf('<option id="stay3" value="close" %s >%s</option>', $selected['close'], lang('close'));

    $ph['select'] = sprintf($ph['select'], implode("\n", $option));

    return parseText($tpl, $ph);
}

function ab_open_draft($id)
{
    $tpl = '<li id="opendraft" class="opendraft mutate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = style("icons_save");
    $ph['alt'] = 'icons_draft';
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

    $tpl = '<li id="createdraft" class="mutate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = style("icons_save");
    $ph['alt'] = 'icons_draft';
    $ph['label'] = lang('create_draft');

    return parseText($tpl, $ph);
}

function ab_cancel($id)
{
    $tpl = '<li id="cancel" class="mutate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = style("icons_cancel");
    $ph['alt'] = 'icons_cancel';
    $ph['label'] = lang('cancel');
    return parseText($tpl, $ph);
}

function ab_move()
{
    $tpl = '<li id="move" class="mutate"><a href="#"><img src="[+icon+]" /> [+label+]</a></li>';
    $ph['icon'] = style("icons_move_document");
    $ph['label'] = lang('move');
    return parseText($tpl, $ph);
}

function ab_duplicate()
{
    $tpl = '<li id="duplicate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = style("icons_resource_duplicate");
    $ph['alt'] = 'icons_resource_duplicate';
    $ph['label'] = lang('duplicate');
    return parseText($tpl, $ph);
}

function ab_delete()
{
    $tpl = '<li id="delete"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = style("icons_delete_document");
    $ph['alt'] = 'icons_delete_document';
    $ph['label'] = lang('delete');
    return parseText($tpl, $ph);
}

function ab_undelete()
{
    $tpl = '<li id="undelete"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = style("icons_undelete_resource");
    $ph['alt'] = 'icons_undelete_document';
    $ph['label'] = lang('undelete_resource');
    return parseText($tpl, $ph);
}

function ab_delete_draft()
{
    $tpl = '<li id="deletedraft"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = style("icons_delete_document");
    $ph['alt'] = 'icons_delete_document';
    $ph['label'] = lang('delete_draft');
    return parseText($tpl, $ph);
}
