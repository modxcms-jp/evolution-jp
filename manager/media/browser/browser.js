var lastImageCtrl;
var lastFileCtrl;

function BrowseServer(ctrl) {
    lastImageCtrl = ctrl;
    window.evoOpenFilePicker(imanager_url);
}

function BrowseFileServer(ctrl) {
    lastFileCtrl = ctrl;
    window.evoOpenFilePicker(fmanager_url);
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
