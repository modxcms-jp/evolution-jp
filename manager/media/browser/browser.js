var lastImageCtrl;
var lastFileCtrl;

// ctrl: 選んだ値を入れる欄の id
// currentValue: ピッカーを開く場所を決める値（省略したら ctrl の欄の値）
function BrowseServer(ctrl, currentValue) {
    lastImageCtrl = ctrl;
    lastFileCtrl = '';
    window.evoOpenFilePicker(imanager_url, pickerCurrentValue(ctrl, currentValue));
}

function BrowseFileServer(ctrl, currentValue) {
    lastFileCtrl = ctrl;
    lastImageCtrl = '';
    window.evoOpenFilePicker(fmanager_url, pickerCurrentValue(ctrl, currentValue));
}

function pickerCurrentValue(ctrl, currentValue) {
    if (currentValue !== undefined) return currentValue;
    var c = document.getElementById(ctrl);
    return c ? c.value : '';
}

function SetUrl(url, width, height, alt) {
    var ctrl = lastFileCtrl || lastImageCtrl;
    lastFileCtrl = '';
    lastImageCtrl = '';
    var c = ctrl ? document.getElementById(ctrl) : null;
    if (!c) return;
    c.value = url;
    // 素の change を起こす（管理画面の未保存の検知と、欄の change のリスナーに届く）
    c.dispatchEvent(new Event('change', {bubbles: true}));
}
