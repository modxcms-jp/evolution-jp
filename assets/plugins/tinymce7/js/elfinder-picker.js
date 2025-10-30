(function (global) {
  'use strict';

  if (global.mceElfinderPicker) {
    return;
  }

  function resolveDefaultUrl() {
    var scripts = document.getElementsByTagName('script');
    var current = scripts[scripts.length - 1];
    if (current && current.src) {
      try {
        var url = new URL(current.src, global.location.href);
        url.pathname = url.pathname.replace(/js\/elfinder-picker\.js$/, 'elfinder/elfinder.html');
        return url.toString();
      } catch (e) {
        console.warn('TinyMCE7: unable to resolve elFinder URL from script src.', e);
      }
    }
    return 'assets/plugins/tinymce7/elfinder/elfinder.html';
  }

  var fallbackUrl = resolveDefaultUrl();

  global.mceElfinderPicker = function (callback, value, meta) {
    var pickerUrl = global.tinymce7ElfinderUrl || fallbackUrl;
    var editor = global.tinymce && global.tinymce.activeEditor;
    if (!editor || !editor.windowManager) {
      console.error('TinyMCE7: active editor instance is not available.');
      return;
    }

    editor.windowManager.openUrl({
      title: 'ファイルマネージャー',
      url: pickerUrl,
      width: 900,
      height: 600,
      onMessage: function (api, message) {
        if (message && message.mceAction === 'fileSelected' && message.data && message.data.file) {
          callback(message.data.file.url, { text: message.data.file.name });
          api.close();
        }
      }
    });
  };
})(window);
