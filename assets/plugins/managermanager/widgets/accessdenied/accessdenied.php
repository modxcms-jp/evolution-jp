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

    // 次にどうすればよいか分かるよう、「戻る」を付ける。リンク先は戻る先が無いとき（直接開いたとき）のダッシュボード
    $message .= sprintf(
        '<p class="aactions"><a id="aback-button" class="abutton aprimary" href="index.php?a=2">%s</a></p>',
        hsc(lang('go_back'))
    );

    // 消すのは編集画面の領域（シェルの #mainPane）の中だけにする。body を対象にすると、
    // シェルではメニュー・ツリー・ログアウトまで消えてしまう。シェルが無い画面では従来どおり body
    $output .= '
    var accessDeniedRoot = document.getElementById("mainPane") || document.body;
    accessDeniedRoot.replaceChildren();
    accessDeniedRoot.insertAdjacentHTML("afterbegin", ' . js_literal('<div id="aback"><div id="amessage">' . $message . '</div></div>') . ');
    // シェルの中で移ってきたとき（shell.js が履歴に付ける目印 shellNav がある）だけ前の画面へ戻る。
    // 直接開いたときは管理画面の外へ戻ってしまうので、リンク先のダッシュボードへ移る
    document.getElementById("aback-button").addEventListener("click", function (e) {
        if (window.history.state && window.history.state.shellNav) {
            e.preventDefault();
            window.history.back();
        }
    });';

    event()->output($output . "\n");
}
