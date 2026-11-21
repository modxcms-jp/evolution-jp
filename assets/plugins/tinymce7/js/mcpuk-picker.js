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

  // width はそのまま、height を新しい縦横比で決める。height だけがあれば width を決める。どちらも無ければ付けない
  function scaledSize(img, size) {
    if (!size || !size.width || !size.height) {
      return {};
    }
    const width = parseInt(img.getAttribute('width'), 10);
    const height = parseInt(img.getAttribute('height'), 10);
    if (width > 0) {
      return {width: width, height: Math.round(width * size.height / size.width)};
    }
    if (height > 0) {
      return {width: Math.round(height * size.width / size.height), height: height};
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
    // ピッカーを使えない利用者には、画像編集を付けない（PHP の EditorInitializer が決める）
    if (settings().imageEdit !== true) {
      return;
    }
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

  const IMAGE_EXTENSIONS_BY_TYPE = Object.freeze({
    'image/jpeg': 'jpg',
    'image/png': 'png',
    'image/gif': 'gif',
    'image/webp': 'webp',
    'image/bmp': 'bmp'
  });

  // 名前を持つファイルはその名前、無ければ image.拡張子 を送る。保存先の名前（pasted-年月日-時分秒 への置き換え）は、サーバー（FileStore）が決める
  function uploadName(blobInfo) {
    const blob = blobInfo.blob();
    if (blob && typeof blob.name === 'string' && blob.name !== '') {
      return blob.name;
    }

    let extension = IMAGE_EXTENSIONS_BY_TYPE[blob && blob.type];
    if (!extension) {
      const match = /\.([A-Za-z0-9]+)$/.exec(blobInfo.filename());
      extension = match ? match[1].toLowerCase() : 'png';
    }
    return 'image.' + extension;
  }

  function uploadFailure(message) {
    return {message: message, remove: true};
  }

  // 貼り付け・ドロップした画像を、ファイルピッカーの API（action=uploadPasted）で保存し、本文に入れる URL で resolve する。
  // TinyMCE の images_upload_handler。失敗は {message, remove: true} で reject し、本文からその画像を外す
  global.mceModxImageUpload = function (blobInfo, progress) {
    return new Promise(function (resolve, reject) {
      const upload = settings().upload;
      if (!upload || !upload.url) {
        reject(uploadFailure('MODX_FILE_PICKER.upload is not defined.'));
        return;
      }

      const form = new global.FormData();
      form.append('action', 'uploadPasted');
      form.append('type', 'images');
      form.append('csrf_token', upload.csrfToken);
      form.append('files[]', blobInfo.blob(), uploadName(blobInfo));

      const xhr = new global.XMLHttpRequest();
      xhr.open('POST', upload.url);
      xhr.upload.onprogress = function (event) {
        if (event.lengthComputable) {
          progress(event.loaded / event.total * 100);
        }
      };
      xhr.onerror = function () {
        reject(uploadFailure('HTTP Error: Could not reach the server'));
      };
      xhr.onload = function () {
        let data = null;
        try {
          data = JSON.parse(xhr.responseText);
        } catch (e) {
          // JSON でない応答（CSRF の 403 は text/plain）は、HTTP の状態で知らせる
        }

        if (data && data.uploaded && data.uploaded.length > 0 && !(data.errors && data.errors.length > 0)) {
          resolve(normalizeUrl(data.uploaded[0].path));
          return;
        }

        const reason = data && data.errors && data.errors[0] && data.errors[0].message
          || data && data.error && data.error.message
          || 'HTTP Error: ' + xhr.status;
        reject(uploadFailure(reason));
      };
      xhr.send(form);
    });
  };

  // 開いたときから本文にある data: 画像（保存時の変換の対象）の src。アップロードの対象から外して、開くたびに保存されないようにする。
  // TinyMCE は本文を読み込むときに base64 の data: 画像を blob: の URL に置き換える（保存するときは data: に戻す）ので、
  // 初期化が終わるまでの SetContent のたびに、本文の blob:・data: の画像の src（置き換え後）を覚える。
  // TinyMCE がアップロードの対象を探す処理より前に呼ばれるよう、setup の時点で付ける
  function rememberExistingImages(editor) {
    const sources = new Set();

    function collect() {
      editor.getBody().querySelectorAll('img[src^="blob:"], img[src^="data:"]').forEach(function (img) {
        sources.add(img.src);
      });
    }

    editor.on('SetContent', collect);
    editor.once('init', function () {
      editor.off('SetContent', collect);
    });
    return sources;
  }

  const existingImagesByEditor = new global.WeakMap();

  // 本文に、アップロード前・中の画像（blob: の URL で、開いたときからある画像でないもの）が残っているか。保存前に待つかどうかの判定
  function hasPendingUploads(editor) {
    const existing = existingImagesByEditor.get(editor);
    if (editor.removed || !editor.getBody() || !existing) {
      return false;
    }
    return Array.prototype.some.call(editor.getBody().querySelectorAll('img[src^="blob:"]'), function (img) {
      return !existing.has(img.src);
    });
  }

  const submitGuardedForms = new global.WeakSet();
  const submittingForms = new global.WeakSet();

  // 元の欄(textarea)のフォームの送信の前に、アップロード中の画像を待つ。
  // フォームごとに 1 つだけ受け手を付ける（capture）。manager/media/script/shell.js の submit の受け手（document の bubble。
  // defaultPrevented なら何もしない）と TinyMCE の submit の受け手（フォームの bubble）より前に呼ばれる。
  // 待っているあいだは preventDefault() で止め、終わったら requestSubmit() で送り直す（送り直した送信は、待つものが無いのでそのまま通る）
  function bindSubmitWait(editor) {
    const textarea = editor.getElement();
    const form = textarea && textarea.form;
    if (!form || submitGuardedForms.has(form)) {
      return;
    }
    submitGuardedForms.add(form);

    form.addEventListener('submit', function (event) {
      if (submittingForms.has(form)) {
        event.preventDefault();
        return;
      }
      const pending = global.tinymce.get().filter(function (candidate) {
        return candidate.getElement() && candidate.getElement().form === form && hasPendingUploads(candidate);
      });
      if (pending.length === 0) {
        return;
      }

      event.preventDefault();
      const submitter = event.submitter;
      submittingForms.add(form);

      // 送らずに終える道では、黙らずに通知を出し、未保存の印を戻す（保存の操作は、documentDirty を false にしてから呼ばれる）
      function cancelSubmit() {
        submittingForms.delete(form);
        if ('documentDirty' in global) {
          global.documentDirty = true;
        }
        editor.notificationManager.open({
          type: 'error',
          text: editor.options.get('modx_upload_not_saved_message')
        });
      }

      global.Promise.all(pending.map(function (candidate) {
        return candidate.uploadImages();
      })).then(function (results) {
        // 失敗した画像は、TinyMCE が本文から外して理由を通知する。画像が欠けたまま保存しないよう、送らずに知らせる。
        // 失敗の印が無くても blob: が残っていれば、その URL を保存しないよう送らない
        const failed = !Array.isArray(results) || results.some(function (items) {
          return !Array.isArray(items) || items.some(function (item) {
            return item && item.status === false;
          });
        });
        if (failed || pending.some(hasPendingUploads)) {
          cancelSubmit();
          return;
        }
        submittingForms.delete(form);
        try {
          if (submitter && submitter.form === form) {
            form.requestSubmit(submitter);
          } else {
            form.requestSubmit();
          }
        } catch (error) {
          cancelSubmit();
        }
      }).catch(cancelSubmit);
    }, true);
  }

  // setup（editor-setup.js）から呼ぶ。アップロードを使うとき（PHP が MODX_FILE_PICKER.upload を渡したとき）だけ働く
  global.mceModxPastedUpload = function (editor) {
    if (!settings().upload) {
      return;
    }

    editor.options.register('modx_upload_not_saved_message', {
      processor: 'string',
      default: 'Saving was cancelled because an image upload did not finish. Please save again.'
    });
    const existingImages = rememberExistingImages(editor);
    existingImagesByEditor.set(editor, existingImages);
    // editorUpload は、エディターの初期化の途中（PreInit の時点）で作られる
    editor.on('PreInit', function () {
      editor.editorUpload.addFilter(function (img) {
        return !existingImages.has(img.src);
      });
    });
    bindSubmitWait(editor);
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
