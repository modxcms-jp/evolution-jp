/*
 * TinyMCE 7 の本文で、href が [~ID~] のリンクを「リンクを開く」で開いたとき、その文書の公開側の URL を開く（管理画面だけ）。
 * TinyMCE はそのままの [~ID~] をサイトの URL に付けて開いてしまい 404 になるため、link プラグインより先に横取りする。
 * 対象: リンクの操作の帯と右クリックのメニューの「リンクを開く」、Ctrl（Mac は Cmd）+ クリック、Alt+Enter。
 * URL は link-list.php?id= から取る（見える範囲の判定はリンクの一覧と同じ）。[~ID~] 以外の href は TinyMCE に任せる。
 * editor-setup.js が window.mceModxLinkOpen(editor) を呼ぶ。
 */
(function (global) {
  'use strict';

  if (global.mceModxLinkOpen) {
    return;
  }

  const TAG_HREF = /^\[~(\d+)~\]$/;
  const UI_SELECTOR = '.tox-pop [aria-label], .tox-menu [aria-label]';
  const contextLinks = new WeakMap();
  let uiBound = false;

  function linkOf(editor, node) {
    return node ? editor.dom.getParent(node, 'a[href]') : null;
  }

  function tagId(link) {
    const match = link ? TAG_HREF.exec((link.getAttribute('href') || '').trim()) : null;
    return match ? match[1] : null;
  }

  // 先に窓を開けておき（クリックの操作の中でないとポップアップを止められる）、URL が分かったらその窓を移す
  function openDocument(editor, id) {
    const win = global.open('', '_blank');
    if (win) {
      win.opener = null;
    }
    const settings = editor.options.get('modx_link_open');
    fetch(settings.url + '?id=' + encodeURIComponent(id), {credentials: 'same-origin'})
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .catch(function () {
        return null;
      })
      .then(function (data) {
        if (data && data.url) {
          if (win) {
            win.location.href = data.url;
          }
          return;
        }
        if (win) {
          win.close();
        }
        editor.notificationManager.open({
          text: settings.missing + ' (ID ' + id + ')',
          type: 'warning',
          timeout: 5000
        });
      });
  }

  // 帯とメニューはエディターの外の要素なので、全体で 1 つの捕捉のリスナーで受ける
  function bindUi() {
    if (uiBound) {
      return;
    }
    uiBound = true;
    document.addEventListener('click', function (event) {
      const editor = global.tinymce && global.tinymce.activeEditor;
      const target = event.target.closest && event.target.closest(UI_SELECTOR);
      if (!editor || !target || !editor.options.isRegistered('modx_link_open')
        || target.getAttribute('aria-label') !== editor.translate('Open link')) {
        return;
      }
      const id = tagId(linkOf(editor, editor.selection.getStart()) || contextLinks.get(editor));
      if (id === null) {
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      openDocument(editor, id);
      editor.focus();
    }, true);
  }

  global.mceModxLinkOpen = function (editor) {
    editor.options.register('modx_link_open', {processor: 'object'});
    if (!editor.options.get('modx_link_open')) {
      return;
    }
    bindUi();
    editor.on('contextmenu', function (event) {
      contextLinks.set(editor, linkOf(editor, event.target));
    });
    // link プラグインの同じ操作より先に受ける（prepend）
    editor.on('click', function (event) {
      const id = tagId(linkOf(editor, event.target));
      const modifier = global.tinymce.Env.os.isMacOS() ? event.metaKey : event.ctrlKey;
      if (id !== null && modifier) {
        event.preventDefault();
        event.stopImmediatePropagation();
        openDocument(editor, id);
      }
    }, true);
    editor.on('keydown', function (event) {
      if (event.keyCode !== 13 || !event.altKey || event.ctrlKey || event.shiftKey || event.metaKey) {
        return;
      }
      const id = tagId(linkOf(editor, editor.selection.getStart()));
      if (id !== null) {
        event.preventDefault();
        event.stopImmediatePropagation();
        openDocument(editor, id);
      }
    }, true);
  };
})(window);
