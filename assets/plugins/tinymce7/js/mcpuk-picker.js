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

  function normalizePathSegment(segment) {
    try {
      return decodeURIComponent(segment).trim();
    } catch (e) {
      return segment.trim();
    }
  }

  function splitCurrentPath(currentValue, type) {
    if (!currentValue && currentValue !== 0) {
      return null;
    }

    const raw = String(currentValue).trim();
    if (!raw) {
      return null;
    }

    let pathname = raw;
    try {
      pathname = new URL(raw, global.location.href).pathname;
    } catch (e) {
      pathname = raw.split('#')[0].split('?')[0];
    }

    const normalizedType = String(type || '').toLowerCase();
    const segments = pathname
      .split('/')
      .map(function (segment) {
        return normalizePathSegment(segment);
      })
      .filter(Boolean);
    const typeIndex = segments.indexOf(normalizedType);

    if (typeIndex === -1 || typeIndex >= segments.length - 1) {
      return null;
    }

    return {
      folder: segments.slice(typeIndex + 1, -1).join('/'),
      select: segments[segments.length - 1]
    };
  }

  function buildUrl(baseUrl, meta, currentValue) {
    const data = meta || {};
    const type = TYPE_MAP[String(data.filetype || '').toLowerCase()] || 'files';
    const separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
    const currentPath = splitCurrentPath(currentValue, type);
    let url = baseUrl + separator + 'type=' + encodeURIComponent(type);

    if (currentPath && currentPath.folder) {
      url += '&folder=' + encodeURIComponent(currentPath.folder);
    }
    if (currentPath && currentPath.select) {
      url += '&select=' + encodeURIComponent(currentPath.select);
    }

    return url;
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

  // TinyMCE公式が案内する、外部UIをTinyMCE自身のダイアログ管理下(iframe)に
  // 表示する方式。file_picker_callbackで直接window.open/EvoShellモーダルを
  // 開く方式と異なり、TinyMCEのコンポーネントツリーの外に出ないため、
  // 「画像の挿入/編集」ダイアログとの競合(コンテキスト破棄)が起きない。
  // iframe内(filebrowser.js)はwindow.parent.postMessageで選択結果を返す。
  function openInEditorDialog(editor, url, onPick) {
    const maxWidth = global.innerWidth || 1024;
    const maxHeight = global.innerHeight || 768;
    const width = Math.max(Math.min(Math.round(maxWidth * 0.8), 1200), 600);
    const height = Math.max(Math.min(Math.round(maxHeight * 0.8), 800), 400);

    return editor.windowManager.openUrl({
      title: 'ファイルブラウザ',
      url: url,
      width: width,
      height: height,
      onMessage: function (api, details) {
        if (details && details.mceAction === 'evoFbPick') {
          api.close();
          onPick(details.url);
        }
      }
    });
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

    // 実機検証の結果、EvoShellモーダル(外部の別モーダル)をTinyMCEダイアログの上に
    // 開くと "component must be in a context to execute" エラーの後エディタごと
    // 解除される事象を確認した。そのため、TinyMCE自身のダイアログ管理下(iframe)に
    // 表示するwindowManager.openUrl()を優先し、非対応環境ではポップアップへ落とす。
    const editor = global.tinymce && global.tinymce.activeEditor;
    if (editor && editor.windowManager && typeof editor.windowManager.openUrl === 'function') {
      openInEditorDialog(editor, buildUrl(baseUrl, meta, value), function (fileUrl) {
        if (typeof callback === 'function') {
          callback(normalizeUrl(fileUrl));
        }
      });
      return;
    }

    const restore = createSetUrlGuard(global.SetUrl, callback);
    const browserWindow = openWindow(buildUrl(baseUrl, meta, value));
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
