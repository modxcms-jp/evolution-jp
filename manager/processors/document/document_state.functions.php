<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

// 削除・公開・非公開の 1 件分の確かめと更新。1 件の操作（a=6・61・62）と、一覧からの一括の操作（a=121）の両方から呼ぶ。
// 確かめる関数は更新せず、操作できない理由を返す。更新する関数は、確かめたあとの文書に使う。キャッシュの消去は呼び出し側が行う

// 文書グループの権限と許可された親の範囲で、その文書を操作できるか
function docIsAccessible($id)
{
    $id = (int)$id;
    return evo()->checkPermissions($id) && manager()->isInAllowedParents($id);
}

/**
 * 削除できるかを確かめる（文書そのもの・リンクされていないか・子孫）
 *
 * @param int $id
 * @return array ['warning' => 止める理由（空なら削除できる）, 'children' => 一緒に削除する子孫の ID]
 */
function docDeleteCheck($id)
{
    $id = (int)$id;
    if ($id == evo()->config['site_start']) {
        $warning = "Document is 'Site start' and cannot be deleted!";
    } elseif ($id == evo()->config['error_page']) {
        $warning = "Document is 'Error page' and cannot be deleted!";
    } elseif ($id == evo()->config['site_unavailable_page']) {
        $warning = "Document is used as the 'Site unavailable page' and cannot be deleted!";
    } elseif (!docIsAccessible($id)) {
        $warning = lang('access_permission_denied');
    } else {
        $linked = docDeleteLinkedBy($id);
        $warning = $linked ? 'Linked by ID:' . implode(', ID:', $linked) : '';
    }
    if ($warning !== '') {
        return ['warning' => $warning, 'children' => []];
    }
    return docDeleteChildren($id);
}

/**
 * 子孫を集める。サイト開始ページ・エラーページ・サイト停止中のページがあれば、削除できない理由を返す
 *
 * @param int $parent
 * @return array ['warning' => 止める理由, 'children' => 子孫の ID]
 */
function docDeleteChildren($parent)
{
    $protected = [
        'site_start' => 'Site start',
        'error_page' => 'Error page',
        'site_unavailable_page' => 'Site unavailable page',
    ];
    $children = [];
    $rs = db()->select('id', '[+prefix+]site_content', "parent='" . (int)$parent . "' AND deleted='0'");
    while ($row = db()->getRow($rs)) {
        foreach ($protected as $key => $label) {
            if ($row['id'] == evo()->config[$key]) {
                return [
                    'warning' => sprintf(
                        "The document you are trying to delete is a folder containing document %s. This document is registered as the '%s' document, and cannot be deleted. Please assign another document as your '%s' document and try again.",
                        $row['id'],
                        $label,
                        $label
                    ),
                    'children' => [],
                ];
            }
        }
        // 深さ優先（子の次にその子孫）の並びで返す
        $children[] = (int)$row['id'];
        $sub = docDeleteChildren($row['id']);
        if ($sub['warning'] !== '') {
            return $sub;
        }
        $children = array_merge($children, $sub['children']);
    }
    return ['warning' => '', 'children' => $children];
}

// [~id~] で参照している文書の ID の配列。無ければ false
function docDeleteLinkedBy($id)
{
    $rs = db()->select('id', '[+prefix+]site_content', "content LIKE '%[~" . (int)$id . "~]%' AND deleted='0'");
    if (!db()->count($rs)) {
        return false;
    }
    $result = [];
    while ($row = db()->getRow($rs)) {
        $result[] = $row['id'];
    }
    return $result;
}

/**
 * 文書と子孫を削除済みにして、OnBeforeDocFormDelete・OnDocFormDelete を発火する
 *
 * @param int $id
 * @param array $children docDeleteCheck() が返した子孫の ID
 * @return string プラグインに止められたときは理由。削除したときは空
 */
function docDeleteExecute($id, array $children)
{
    $id = (int)$id;
    $params = ['id' => $id, 'children' => $children, 'enableProcess' => true];
    evo()->invokeEvent('OnBeforeDocFormDelete', $params);
    if ($params['enableProcess'] == false) {
        return 'The deletion process was interrupted by plugin.';
    }

    $field = [
        'deleted' => '1',
        'deletedby' => (int)evo()->getLoginUserID(),
        'deletedon' => time(),
    ];
    if ($children) {
        $rs = db()->update($field, '[+prefix+]site_content', 'id IN(' . implode(',', array_map('intval', $children)) . ')');
        if (!$rs) {
            exit("Something went wrong while trying to set the document's children to deleted status...");
        }
    }
    $rs = db()->update($field, '[+prefix+]site_content', "id='{$id}'");
    if (!$rs) {
        exit('Something went wrong while trying to set the document to deleted status...');
    }

    $params = ['id' => $id, 'children' => $children];
    evo()->invokeEvent('OnDocFormDelete', $params);
    return '';
}

/**
 * 公開・非公開の操作ができるかを確かめる
 *
 * @param int $id
 * @param bool $publish 公開なら true（view_unpublished の確かめも行う）
 * @return string 止める理由（言語キー。非公開でサイト開始ページのときは文）。空なら操作できる
 */
function docPublishCheck($id, $publish)
{
    $id = (int)$id;
    if (!docIsAccessible($id)) {
        return 'access_permission_denied';
    }
    if (!$publish && $id == evo()->config['site_start']) {
        // サイト開始ページは非公開にできない（リソースの保存と同じ文言）
        evo()->loadExtension('DocAPI');
        return evo()->doc->startDocPubError(0, 0, 0);
    }
    if ($publish && !evo()->hasPermission('view_unpublished')) {
        $doc = db()->getObject('site_content', "id='{$id}'");
        if (!$doc || evo()->getLoginUserID() != $doc->publishedby) {
            return 'error_no_privileges';
        }
    }
    return '';
}

// 文書を公開する（イベントは docPublishFireEvent()。予約公開などと同じく、キャッシュの消去のあとに発火する）
function docPublishExecute($id)
{
    $id = (int)$id;
    $doc = db()->getObject('site_content', "id='{$id}'");
    $now = time();
    $field = ['published' => 1];
    if ($now < $doc->pub_date) {
        $field['pub_date'] = 0;
    }
    if ($doc->unpub_date < $now) {
        $field['unpub_date'] = 0;
    }
    $field['publishedby'] = (int)evo()->getLoginUserID();
    $field['publishedon'] = $now;
    $field['editedon'] = $now;
    $field['editedby'] = (int)evo()->getLoginUserID();
    $rs = db()->update($field, '[+prefix+]site_content', "id='{$id}'");
    if (!$rs) {
        exit('An error occured while attempting to publish the document.');
    }
}

// OnDocPublished を発火する
function docPublishFireEvent($id)
{
    $tmp = ['docid' => (int)$id, 'type' => 'manual'];
    evo()->invokeEvent('OnDocPublished', $tmp);
}

// 文書を非公開にして OnDocUnPublished を発火する
function docUnpublishExecute($id)
{
    $id = (int)$id;
    $doc = db()->getObject('site_content', "id='{$id}'");
    $field = ['published' => 0];
    if ($doc->pub_date < time()) {
        $field['pub_date'] = 0;
    }
    $field['publishedby'] = 0;
    $field['publishedon'] = 0;
    $field['editedon'] = time();
    $field['editedby'] = (int)evo()->getLoginUserID();
    $rs = db()->update($field, '[+prefix+]site_content', "id='{$id}'");
    if (!$rs) {
        exit('An error occured while attempting to unpublish the document.');
    }

    $tmp = ['docid' => $id, 'type' => 'manual'];
    evo()->invokeEvent('OnDocUnPublished', $tmp);
}
