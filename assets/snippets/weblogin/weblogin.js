// WebLogin スニペットのフォームの操作（weblogin.inc.php が読む）。
// ログインとパスワードの再発行のフォーム（既定のテンプレート、またはチャンクの onclick など）が、これらの関数を名前で呼ぶ。
function webLoginShowForm(i) {
    var a = document.getElementById('WebLoginLayer0');
    var b = document.getElementById('WebLoginLayer2');
    if (i == 1 && a && b) {
        a.style.display = "block";
        b.style.display = "none";
        document.forms['loginreminder'].txtpwdrem.value = 0;
    } else if (i == 2 && a && b) {
        a.style.display = "none";
        b.style.display = "block";
        document.forms['loginreminder'].txtpwdrem.value = 1;
    }
}

function webLoginCheckRemember() {
    if (document.loginfrm.rememberme.value == 1) {
        document.loginfrm.rememberme.value = 0;
    } else {
        document.loginfrm.rememberme.value = 1;
    }
}

function webLoginEnter(nextfield, event) {
    if (event && event.keyCode == 13) {
        if (nextfield.name == 'cmdweblogin') {
            document.loginfrm.submit();
            return false;
        }
        nextfield.focus();
        return false;
    }
    return true;
}
