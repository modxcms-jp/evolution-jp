<?php
/**
 * mm_widget_accessdenied
 * @version 1.1 (2012-11-13)
 *
 * Close access for some documents by ids.
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
        $message = '<strong>Access denied</strong><br>Access to current document closed for security reasons.';
    }

    // 編集画面の中身は消す（編集させない）。消すのは編集画面の領域（シェルの #mainPane）の中だけにする。
    // body を対象にすると、シェルではメニュー・ツリー・ログアウトまで消えてしまう。シェルが無い画面では従来どおり body。
    // メッセージは管理画面のダイアログ（EvoDialog）で、画面全体を覆うモーダルとして出す。
    // 「戻る」は、シェルの中で移ってきたとき（shell.js が履歴に付ける目印 shellNav がある）だけ前の画面へ戻る。
    // 直接開いたときは管理画面の外へ戻ってしまうので、ダッシュボードへ移る。このとき履歴を積むと、
    // ダッシュボードでブラウザの「戻る」を押すたびにこの警告へ戻るので、拒否した URL の履歴を置き換える
    $output .= '
    (document.getElementById("mainPane") || document.body).replaceChildren();
    EvoDialog.alert(' . js_literal($message) . ', "", function () {
        if (window.history.state && window.history.state.shellNav) {
            window.history.back();
        } else {
            window.location.replace("index.php?a=2");
        }
    }, { icon: "warning", okLabel: ' . js_literal(lang('go_back')) . ' });';

    event()->output($output . "\n");
}
