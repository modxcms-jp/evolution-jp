var lastImageCtrl;
var lastFileCtrl;

function BrowseServer(ctrl) {
    lastImageCtrl = ctrl;
    var c = document.getElementById(ctrl);
    window.evoOpenFilePicker(imanager_url, c ? c.value : '');
}

function BrowseFileServer(ctrl) {
    lastFileCtrl = ctrl;
    var c = document.getElementById(ctrl);
    window.evoOpenFilePicker(fmanager_url, c ? c.value : '');
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
