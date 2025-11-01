(function (global) {
  'use strict';

  if (global.mceModxFilePicker) {
    return;
  }

  function buildUrl(baseUrl, meta) {
    var typeMap = {
      image: 'images',
      media: 'media',
      file: 'files'
    };
    var type = typeMap[meta && meta.filetype] || 'files';
    var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
    return baseUrl + separator + 'type=' + encodeURIComponent(type);
  }

  function openWindow(url) {
    var width = Math.max(Math.min(Math.round((global.innerWidth || global.screen.width || 1024) * 0.7), 1280), 600);
    var height = Math.max(Math.min(Math.round((global.innerHeight || global.screen.height || 768) * 0.7), 900), 400);
    var features = [
      'width=' + width,
      'height=' + height,
      'resizable=yes',
      'scrollbars=yes'
    ].join(',');
    return global.open(url, 'modxMcpukBrowser', features);
  }

  function createSetUrlGuard(previous, callback) {
    var cleaned = false;
    function restore() {
      if (cleaned) {
        return;
      }
      cleaned = true;
      if (previous) {
        global.SetUrl = previous;
      } else {
        try {
          delete global.SetUrl;
        } catch (e) {
          global.SetUrl = undefined;
        }
      }
    }

    global.SetUrl = function (fileUrl) {
      try {
        if (typeof callback === 'function') {
          callback(fileUrl);
        }
      } finally {
        restore();
      }
    };

    return restore;
  }

  global.mceModxFilePicker = function (callback, value, meta) {
    var baseUrl = global.MODX_FILE_BROWSER_URL;
    if (!baseUrl) {
      console.error('TinyMCE7: MODX_FILE_BROWSER_URL is not defined.');
      return;
    }

    var restore = createSetUrlGuard(global.SetUrl, callback);
    var browserWindow = openWindow(buildUrl(baseUrl, meta || {}));
    if (!browserWindow) {
      restore();
      global.alert('ファイルブラウザを開けませんでした。ポップアップを許可してください。');
      return;
    }

    var poll = global.setInterval(function () {
      if (browserWindow.closed) {
        global.clearInterval(poll);
        restore();
      }
    }, 500);
  };
})(window);
