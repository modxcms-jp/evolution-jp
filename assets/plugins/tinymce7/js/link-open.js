/*
 * TinyMCE 7 の本文で、href が [~ID~] のリンクを「リンクを開く」で開いたとき、その文書の公開側の URL を開く（管理画面だけ）。
 * TinyMCE の link プラグインは、帯・メニュー・キーボード・タッチ・ツールバー・Ctrl+クリック・Alt+Enter のどれでも、
 * 内部の openLink(url) に集まり、target="_blank" rel="noreferrer noopener" の一時的な <a> を body の直下に足して click を送る。
 * その click を document の捕捉で受け、href が（document_base_url の後ろに）[~ID~] の形で終わるものだけ、その文書の公開側の URL へ差し替える。
 * 開くリンクの選び方は TinyMCE のまま。URL は link-list.php?id= から取る（見える範囲の判定はリンクの一覧と同じ）。
 * editor-setup.js が window.mceModxLinkOpen(editor) を呼ぶ。
 */
(function (global) {
  'use strict';

  if (global.mceModxLinkOpen) {
    return;
  }

  // [~ID~]・[~ID?引数~] の後ろに ?クエリ や #フラグメント が続いてもよい。前には document_base_url が付く
  const TAG_HREF = /\[~\s*(\d+)\s*(?:\?([^~]*))?~\]([?#][\s\S]*)?$/;

  // 引数は URL の後ろに足す。#… は本文の側にあればそれ、無ければ文書の URL のもの（ウェブリンクの #節 など）を残す
  function buildUrl(base, innerQuery, tail) {
    const splitHash = function (value) {
      const at = value.indexOf('#');
      return at < 0 ? [value, ''] : [value.slice(0, at), value.slice(at)];
    };
    const [url, baseHash] = splitHash(base);
    const [tailQuery, tailHash] = splitHash(tail);
    const query = [innerQuery, tailQuery.replace(/^\?/, '')].filter(Boolean).join('&');
    return url + (query ? (url.indexOf('?') < 0 ? '?' : '&') + query : '') + (tailHash || baseHash);
  }

  function notify(editor, text) {
    editor.notificationManager.open({text: text, type: 'warning', timeout: 5000});
  }

  // 先に窓を開けておき（ユーザーの操作の中でないとポップアップを止められる）、URL が分かったらその窓を移す
  function openDocument(editor, match) {
    const settings = editor.options.get('modx_link_open');
    const win = global.open('', '_blank');
    if (!win) {
      notify(editor, settings.blocked);
      return;
    }
    win.opener = null;
    const fail = function (text) {
      win.close();
      notify(editor, text);
    };
    fetch(settings.url + '?id=' + encodeURIComponent(match[1]), {credentials: 'same-origin'})
      .then(function (response) {
        if (response.status === 404) {
          fail(settings.missing + ' (ID ' + match[1] + ')');
          return null;
        }
        return response.ok ? response.json() : Promise.reject(new Error(String(response.status)));
      })
      .then(function (data) {
        if (data) {
          win.location.href = buildUrl(data.url, match[2] || '', match[3] || '');
        }
      })
      .catch(function () {
        fail(settings.failed);
      });
  }

  function bind() {
    // 一時的な <a> は body の直下・target=_blank・rel=noreferrer noopener。本文や UI の中のリンクとは見分けられる
    document.addEventListener('click', function (event) {
      const link = event.target;
      const editor = global.tinymce && global.tinymce.activeEditor;
      if (!editor || !link || link.nodeName !== 'A' || link.parentNode !== document.body
        || link.getAttribute('target') !== '_blank' || link.getAttribute('rel') !== 'noreferrer noopener'
        || !editor.options.isRegistered('modx_link_open') || !editor.options.get('modx_link_open')) {
        return;
      }
      const match = TAG_HREF.exec((link.getAttribute('href') || '').replace(/%20/g, ' '));
      if (!match) {
        return;
      }
      event.preventDefault();
      openDocument(editor, match);
    }, true);
  }

  let bound = false;

  global.mceModxLinkOpen = function (editor) {
    editor.options.register('modx_link_open', {processor: 'object'});
    if (!editor.options.get('modx_link_open') || bound) {
      return;
    }
    bound = true;
    bind();
  };
})(window);
