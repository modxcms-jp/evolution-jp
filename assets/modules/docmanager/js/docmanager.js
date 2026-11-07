function getCookie(name) {
    var dc = document.cookie;
    var prefix = name + "=";
    var begin = dc.indexOf("; " + prefix);
    if (begin == -1) {
        begin = dc.indexOf(prefix);
        if (begin != 0) return null;
    } else {
        begin += 2;
    }
    var end = document.cookie.indexOf(";", begin);
    if (end == -1) {
        end = dc.length;
    }
    return unescape(dc.substring(begin + prefix.length, end));
}

function getSelectedRadio(buttonGroup) {
    if (buttonGroup[0]) {
        for (var i = 0; i < buttonGroup.length; i++) {
            if (buttonGroup[i].checked) {
                return i;
            }
        }
    } else {
        if (buttonGroup.checked) {
            return 0;
        }
    }
    return -1;
}

function getSelectedRadioValue(buttonGroup) {
    var i = getSelectedRadio(buttonGroup);
    if (i == -1) {
        return '';
    } else {
        if (buttonGroup[i]) {
            return buttonGroup[i].value;
        } else {
            return buttonGroup.value;
        }
    }
}

function changeOtherLabels() {
    var choice1 = document.getElementById('choice_label_1');
    var choice2 = document.getElementById('choice_label_2');

    const misc = document.getElementById('misc').value;

    // 項目 n（1〜6）の選択肢の文言は option(2n-1) と option(2n) にある
    if (['1', '2', '3', '4', '5', '6'].includes(misc)) {
        const n = Number(misc);
        choice1.innerHTML = document.getElementById('option' + (n * 2 - 1)).value;
        choice2.innerHTML = document.getElementById('option' + (n * 2)).value;
    } else if (misc == '0') {
        choice1.innerHTML = " - ";
        choice2.innerHTML = " - ";
    }
}

function postForm() {
    var tabActiveID = getCookie("webfxtab_docManagerPane");

    if (tabActiveID == '0' || tabActiveID == null) {
        document.getElementById('tabaction').value = 'changeTemplate';
        document.getElementById('newvalue').value = getSelectedRadioValue(document.template.id);

        document.getElementById('range').submit();
    } else if (tabActiveID == '1') {
        document.getElementById('pids_tv').value = document.getElementById('pids').value;
        document.getElementById('template_id').value = getSelectedRadioValue(document.templatevariables.tid);

        document.templatevariables.submit();
    } else if (tabActiveID == '2') {
        document.getElementById('tabaction').value = getSelectedRadioValue(document.docgroups.tabAction);
        document.getElementById('newvalue').value = getSelectedRadioValue(document.docgroups.docgroupid);

        document.getElementById('range').submit();
    } else if (tabActiveID == '3') {
        /* handled separately using save() function */
    } else if (tabActiveID == '4') {
        document.getElementById('tabaction').value = 'changeOther';

        document.getElementById('setoption').value = document.other.misc.value;
        document.getElementById('newvalue').value = getSelectedRadioValue(document.other.choice);

        document.getElementById('pubdate').value = document.dates.date_pubdate.value;
        document.getElementById('unpubdate').value = document.dates.date_unpubdate.value;
        document.getElementById('createdon').value = document.dates.date_createdon.value;
        document.getElementById('editedon').value = document.dates.date_editedon.value;

        document.getElementById('author_createdby').value = document.authors.author_createdby.value;
        document.getElementById('author_editedby').value = document.authors.author_editedby.value;

        document.getElementById('range').submit();
    }
}

function hideInteraction() {
    var tabActiveID = getCookie("webfxtab_docManagerPane");
    var tvloading = document.getElementById('tvloading');
    var interaction = document.getElementById('interaction');
    if (tabActiveID == '1' && tvloading) {
        tvloading.style.display = 'none';
    }
    if (tabActiveID == '3') {
        if (interaction) {
            interaction.style.display = 'none';
        }
        if (parent.tree) {
            parent.tree.ca = 'move';
        }
    } else {
        if (interaction) {
            interaction.style.display = '';
        }
        if (parent.tree) {
            parent.tree.ca = '';
        }
    }

    return true;
}

// シェルの遷移では同じ window で script が読み直されるので、この script が文書から外れたら(画面が差し替わったら) click の受け手を外す。
// モーダルの開け閉めでも evoshell:unload は出るので、それでは外さず isConnected で見る。
// currentScript は classic script の実行中は取れる。取れない(null)ときは外せないので、そのまま残す。
var docmanagerMarker = document.currentScript;
(function (marker) {
    function onClick() {
        if (marker && !marker.isConnected) {
            document.removeEventListener('click', onClick, false);
            return;
        }
        hideInteraction();
    }
    document.addEventListener('click', onClick, false);
})(docmanagerMarker);

Evo.ready(hideInteraction);
