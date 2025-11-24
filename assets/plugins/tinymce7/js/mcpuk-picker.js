(function (global) {
  'use strict';

  if (global.mceModxFilePicker) {
    return;
  }

  const TYPE_MAP = Object.freeze({
    image: 'images',
    media: 'media',
    file: 'files'
  });

  function buildUrl(baseUrl, meta) {
    const data = meta || {};
    const type = TYPE_MAP[String(data.filetype || '').toLowerCase()] || 'files';
    const separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
    return baseUrl + separator + 'type=' + encodeURIComponent(type);
  }

  function sanitizeUrlSegment(value) {
    return value.replace(/\/+$/, '');
  }

  function sanitizePath(value) {
    return value.replace(/^\/+/, '');
  }

  function openWindow(url) {
    const maxWidth = global.innerWidth || (global.screen && global.screen.width) || 1024;
    const maxHeight = global.innerHeight || (global.screen && global.screen.height) || 768;
    const width = Math.max(Math.min(Math.round(maxWidth * 0.7), 1280), 600);
    const height = Math.max(Math.min(Math.round(maxHeight * 0.7), 900), 400);
    const features = [
      'width=' + width,
      'height=' + height,
      'resizable=yes',
      'scrollbars=yes'
    ].join(',');
    return global.open(url, 'modxMcpukBrowser', features);
  }

  function normalizeUrl(fileUrl) {
    if (!fileUrl && fileUrl !== 0) {
      return fileUrl;
    }

    let url = String(fileUrl).trim();
    if (!url) {
      return url;
    }

    if (/^(?:[a-z][a-z0-9+\-.]*:|\/\/|\/)/i.test(url)) {
      return url;
    }

    url = url.replace(/^(?:\.\.\/)+/, '');

    const siteUrl = String(global.MODX_SITE_URL || '').trim();
    if (siteUrl) {
      return sanitizeUrlSegment(siteUrl) + '/' + sanitizePath(url);
    }

    const baseUrl = String(global.MODX_BASE_URL || '').trim();
    if (baseUrl) {
      return sanitizeUrlSegment(baseUrl) + '/' + sanitizePath(url);
    }

    return '/' + sanitizePath(url);
  }

  function createSetUrlGuard(previous, callback) {
    let cleaned = false;
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
          callback(normalizeUrl(fileUrl));
        }
      } finally {
        restore();
      }
    };

    return restore;
  }

  global.mceModxFilePicker = function (callback, value, meta) {
    const baseUrl = global.MODX_FILE_BROWSER_URL;
    if (!baseUrl) {
      console.error('TinyMCE7: MODX_FILE_BROWSER_URL is not defined.');
      return;
    }

    const restore = createSetUrlGuard(global.SetUrl, callback);
    const browserWindow = openWindow(buildUrl(baseUrl, meta));
    if (!browserWindow) {
      restore();
      global.alert('ファイルブラウザを開けませんでした。ポップアップを許可してください。');
      return;
    }

    const poll = global.setInterval(function () {
      if (browserWindow.closed) {
        global.clearInterval(poll);
        restore();
      }
    }, 500);
  };
})(window);
