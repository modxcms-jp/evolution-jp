<?php
/**
 * mm_widget_accessdenied
 * @version 1.1 (2012-11-13)
 *
 * Close access for some documents by ids.
 * Icon by designmagus.com
 * Originally written by Metaller
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_widget_accessdenied/1.1
 *
 * @copyright 2012
 */

function mm_widget_accessdenied($ids = '', $message = '', $roles = '')
{

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles)) {
        return;
    }

    if (!in_array((int)getv('id'), makeArray($ids))) {
        return;
    }

    $output = "//  -------------- accessdenied widget include ------------- \n";
    if (!$message) {
        $message = '<span>Access denied</span>Access to current document closed for security reasons.';
    }
    // CSS を変えたときにブラウザの古いキャッシュが残らないよう、更新時刻を付ける
    $css = 'assets/plugins/managermanager/widgets/accessdenied/accessdenied.css';
    $output .= includeCss(MODX_BASE_URL . $css . '?' . filemtime(MODX_BASE_PATH . $css));

    // 次にどうすればよいか分かるよう、行き先のボタンを付ける。
    // フォルダなら、中のリソースの一覧（フォルダ自体は編集できなくても、中は編集できることが多い）
    $id = (int)getv('id');
    $buttons = '';
    if (db()->getValue('isfolder', '[+prefix+]site_content', "id={$id}")) {
        $buttons .= sprintf(
            '<a class="abutton aprimary" href="index.php?a=120&amp;id=%d">%s</a>',
            $id,
            hsc(lang('view_child_resources_in_container'))
        );
    }
    $buttons .= sprintf('<a class="abutton" href="index.php?a=2">%s</a>', hsc(lang('home')));
    $message .= '<p class="aactions">' . $buttons . '</p>';

    // 消すのは編集画面の領域（シェルの #mainPane）の中だけにする。body を対象にすると、
    // シェルではメニュー・ツリー・ログアウトまで消えてしまう。シェルが無い画面では従来どおり body
    $output .= '
    var accessDeniedRoot = document.getElementById("mainPane") || document.body;
    accessDeniedRoot.replaceChildren();
    accessDeniedRoot.insertAdjacentHTML("afterbegin", ' . js_literal('<div id="aback"><div id="amessage">' . $message . '</div></div>') . ');';

    event()->output($output . "\n");
}
