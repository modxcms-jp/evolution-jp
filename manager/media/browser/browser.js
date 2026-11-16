var lastImageCtrl;
var lastFileCtrl;

// ctrl: 選んだ値を入れる欄の id
// currentValue: ピッカーを開く場所を決める値（省略したら ctrl の欄の値）
function BrowseServer(ctrl, currentValue) {
    lastImageCtrl = ctrl;
    window.evoOpenFilePicker(imanager_url, pickerCurrentValue(ctrl, currentValue));
}

function BrowseFileServer(ctrl, currentValue) {
    lastFileCtrl = ctrl;
    window.evoOpenFilePicker(fmanager_url, pickerCurrentValue(ctrl, currentValue));
}

function pickerCurrentValue(ctrl, currentValue) {
    if (currentValue !== undefined) return currentValue;
    var c = document.getElementById(ctrl);
    return c ? c.value : '';
}

function SetUrl(url, width, height, alt) {
    if (lastFileCtrl) {
        var c = document.getElementById(lastFileCtrl);
        if (c) c.value = url;
        lastFileCtrl = '';
    } else if (lastImageCtrl) {
        var c = document.getElementById(lastImageCtrl);
        if (c) c.value = url;
        lastImageCtrl = '';
    } else {

    }
}
