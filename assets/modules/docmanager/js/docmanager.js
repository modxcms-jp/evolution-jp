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

    if ($j('#misc').val() == '1') {
        choice1.innerHTML = $j('#option1').val();
        choice2.innerHTML = $j('#option2').val();
    } else if ($j('#misc').val() == '2') {
        choice1.innerHTML = $j('#option3').val();
        choice2.innerHTML = $j('#option4').val();
    } else if ($j('#misc').val() == '3') {
        choice1.innerHTML = $j('#option5').val();
        choice2.innerHTML = $j('#option6').val();
    } else if ($j('#misc').val() == '4') {
        choice1.innerHTML = $j('#option7').val();
        choice2.innerHTML = $j('#option8').val();
    } else if ($j('#misc').val() == '5') {
        choice1.innerHTML = $j('#option9').val();
        choice2.innerHTML = $j('#option10').val();
    } else if ($j('#misc').val() == '6') {
        choice1.innerHTML = $j('#option11').val();
        choice2.innerHTML = $j('#option12').val();
    } else if ($j('#misc').val() == '0') {
        choice1.innerHTML = " - ";
        choice2.innerHTML = " - ";
    }
}

function postForm() {
    var tabActiveID = getCookie("webfxtab_docManagerPane");

    if (tabActiveID == '0' || tabActiveID == null) {
        document.getElementById('tabaction').value = 'changeTemplate';
        document.getElementById('newvalue').value = getSelectedRadioValue(document.template.id);

        document.range.submit();
    } else if (tabActiveID == '1') {
        document.getElementById('pids_tv').value = document.getElementById('pids').value;
        document.getElementById('template_id').value = getSelectedRadioValue(document.templatevariables.tid);

        document.templatevariables.submit();
    } else if (tabActiveID == '2') {
        document.getElementById('tabaction').value = getSelectedRadioValue(document.docgroups.tabAction);
        document.getElementById('newvalue').value = getSelectedRadioValue(document.docgroups.docgroupid);

        document.range.submit();
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

        document.range.submit();
    }
}

function hideInteraction() {
    var tabActiveID = getCookie("webfxtab_docManagerPane");
    if (tabActiveID == '1') {
        document.getElementById('tvloading').style.display = 'none';
    }
    if (tabActiveID == '3') {
        if (document.getElementById('interaction')) {
            document.getElementById('interaction').style.display = 'none';
        }
        parent.tree.ca = 'move';
    } else {
        document.getElementById('interaction').style.display = '';
        parent.tree.ca = '';
    }

    return true;
}

if (document.addEventListener) {
    document.addEventListener('DOMContentLoaded', hideInteraction, false);
    document.addEventListener('click', hideInteraction, false);
} else if (document.attachEvent) {
    document.attachEvent('onreadystatechange', function() {
        if (document.readyState === 'complete') {
            hideInteraction();
        }
    });
    document.attachEvent('onclick', hideInteraction);
}