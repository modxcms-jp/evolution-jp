(function (global) {
  'use strict';

  if (global.mceModxFilePicker) {
    return;
  }

  // PHP（FileBrowserResolver）が window.MODX_FILE_PICKER として渡す値
  function settings() {
    return global.MODX_FILE_PICKER || {};
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
      title: 'ファイルピッカー',
      url: url,
      width: width,
      height: height,
      onMessage: function (api, details) {
        if (!details) {
          return;
        }
        if (details.mceAction === 'evoFbPick') {
          api.close();
          onPick(details.url);
        } else if (details.mceAction === 'evoFbClose') {
          api.close();
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

    if (settings().stripImagePaths === true) {
      return sanitizePath(url);
    }

    const siteUrl = String(settings().siteUrl || '').trim();
    if (siteUrl) {
      return sanitizeUrlSegment(siteUrl) + '/' + sanitizePath(url);
    }

    const baseUrl = String(settings().baseUrl || '').trim();
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

  // src を、エディターの基準 URL から見た絶対 URL にし、{origin, segments（要素ごとに decodeURIComponent）} にする。
  // 画像の判定と、上書きかどうかの比較で同じ正規化を使う
  function parseSiteUrl(editor, src) {
    if (!src) {
      return null;
    }
    try {
      const url = new URL(String(src), editor.documentBaseURI.getURI());
      return {
        origin: url.origin,
        segments: url.pathname.split('/').map(normalizePathSegment)
      };
    } catch (e) {
      return null;
    }
  }

  function sameFile(a, b) {
    return !!a && !!b && a.origin === b.origin && a.segments.join('/') === b.segments.join('/');
  }

  // 画像の src が、ファイルピッカーの種類のフォルダ（rb_base_url の下の images・media・files）の
  // 画像編集できる拡張子の画像のときだけ {type, folder, name} を返す。
  // それ以外（data:・blob:・別オリジン・範囲外・ピッカーと別のオリジンの rb_base_url）は null
  function resolveSiteImage(editor, img) {
    const rbBaseUrl = String(settings().rbBaseUrl || '');
    if (!rbBaseUrl || !img) {
      return null;
    }

    const target = parseSiteUrl(editor, img.getAttribute('src'));
    const base = parseSiteUrl(editor, rbBaseUrl);
    if (!target || !base || target.origin !== base.origin || base.origin !== global.location.origin) {
      return null;
    }

    const baseSegments = base.segments.filter(Boolean);
    const rest = target.segments.filter(Boolean);
    if (rest.length < baseSegments.length + 2 || baseSegments.some(function (segment, index) {
      return rest[index] !== segment;
    })) {
      return null;
    }

    const segments = rest.slice(baseSegments.length);
    const type = segments[0];
    const name = segments[segments.length - 1];
    const extension = name.indexOf('.') === -1 ? '' : name.split('.').pop().toLowerCase();
    if ((settings().types || []).indexOf(type) === -1 || (settings().editableExtensions || []).indexOf(extension) === -1) {
      return null;
    }

    return {type: type, folder: segments.slice(1, -1).join('/'), name: name};
  }

  function openImageEditor(editor, img) {
    const target = resolveSiteImage(editor, img);
    const baseUrl = settings().browserUrl;
    if (!target || !baseUrl) {
      return;
    }

    const separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
    let url = baseUrl + separator + 'type=' + encodeURIComponent(target.type);
    if (target.folder) {
      url += '&folder=' + encodeURIComponent(target.folder);
    }
    url += '&select=' + encodeURIComponent(target.name) + '&edit=1';

    openInEditorDialog(editor, url, function (path) {
      replaceImage(editor, img, normalizeUrl(path));
    });
  }

  function loadImageSize(url) {
    return new Promise(function (resolve) {
      const image = new global.Image();
      image.onload = function () {
        resolve({width: image.naturalWidth, height: image.naturalHeight});
      };
      image.onerror = function () {
        resolve(null);
      };
      image.src = url;
    });
  }

  // width はそのまま、height を新しい縦横比で決める。height だけがあれば width を決める。どちらも無ければ付けない。
  // ただし新しい画像より大きくはしない（小さくリサイズした画像を、元の大きさに引き伸ばして粗く出さない）
  function scaledSize(img, size) {
    if (!size || !size.width || !size.height) {
      return {};
    }
    const width = parseInt(img.getAttribute('width'), 10);
    const height = parseInt(img.getAttribute('height'), 10);
    if (width > 0) {
      const shownWidth = Math.min(width, size.width);
      return {width: shownWidth, height: Math.round(shownWidth * size.height / size.width)};
    }
    if (height > 0) {
      const shownHeight = Math.min(height, size.height);
      return {width: Math.round(shownHeight * size.width / size.height), height: shownHeight};
    }
    return {};
  }

  function replaceImage(editor, img, url) {
    if (!url || editor.removed || !editor.getBody().contains(img)) {
      return;
    }

    const current = img.getAttribute('data-mce-src') || img.getAttribute('src');
    const overwritten = sameFile(parseSiteUrl(editor, current), parseSiteUrl(editor, url));
    // 上書きのときは同じ URL の古い画像がブラウザの画像キャッシュに残るので、
    // 大きさの取得と表示にはクエリ付きの URL を使う。本文に残る URL（data-mce-src）はクエリなしのまま
    const shown = overwritten
      ? url + (url.indexOf('?') === -1 ? '?' : '&') + '_evofb=' + Date.now()
      : url;
    const removed = function () {
      return editor.removed || !editor.getBody().contains(img);
    };

    loadImageSize(editor.documentBaseURI.toAbsolute(shown)).then(function (size) {
      if (removed()) {
        return;
      }
      const dimensions = scaledSize(img, size);
      const attributes = {
        src: shown,
        'data-mce-src': url,
        srcset: null
      };
      if (size) {
        attributes.width = dimensions.width || null;
        attributes.height = dimensions.height || null;
      }
      editor.undoManager.transact(function () {
        editor.dom.setAttribs(img, attributes);
      });
      editor.nodeChanged();
    });
  }

  function isEditableTarget(node) {
    return !!node && node.nodeName === 'IMG'
      && !node.hasAttribute('data-mce-object')
      && !node.hasAttribute('data-mce-placeholder');
  }

  global.mceModxImageEdit = function (editor) {
    editor.options.register('modx_image_edit_label', {processor: 'string', default: 'Edit image'});

    editor.on('DblClick', function (event) {
      if (!isEditableTarget(event.target) || !resolveSiteImage(editor, event.target)) {
        return;
      }
      event.preventDefault();
      openImageEditor(editor, event.target);
    });

    editor.ui.registry.addButton('modxImageEdit', {
      icon: 'edit-image',
      tooltip: editor.options.get('modx_image_edit_label'),
      onSetup: function (api) {
        function update() {
          const node = editor.selection.getNode();
          api.setEnabled(isEditableTarget(node) && !!resolveSiteImage(editor, node));
        }
        update();
        editor.on('NodeChange', update);
        return function () {
          editor.off('NodeChange', update);
        };
      },
      onAction: function () {
        const node = editor.selection.getNode();
        if (isEditableTarget(node)) {
          openImageEditor(editor, node);
        }
      }
    });

    editor.ui.registry.addContextToolbar('modxImageEdit', {
      predicate: isEditableTarget,
      items: 'modxImageEdit',
      position: 'node',
      scope: 'node'
    });
  };

  global.mceModxFilePicker = function (callback, value, meta) {
    const baseUrl = settings().browserUrl;
    if (!baseUrl) {
      console.error('TinyMCE7: MODX_FILE_PICKER.browserUrl is not defined.');
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
      global.alert('ファイルピッカーを開けませんでした。ポップアップを許可してください。');
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
