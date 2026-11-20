/*
 * TinyMCE 7 の config.setup から呼ぶ、エディター共通の初期設定。
 * 設定ファイルやサイトの setup を先に呼んだあとで、EditorInitializer が window.tinymce7EditorSetup(editor) を呼ぶ。
 */
(function (global) {
  'use strict';

  if (global.tinymce7EditorSetup) {
    return;
  }

  // 本文の変更を管理画面の未保存の印(documentDirty)に反映する。iframe の中の変更は input 欄の change では拾えない
  function bindDocumentDirty(editor) {
    editor.on('dirty', function () {
      if ('documentDirty' in global) {
        global.documentDirty = true;
      }
    });
  }

  // リッチテキストのエディタは、内容が変わったら元の欄(textarea)に bubbles の input イベントを送る決まり。
  // iframe の中の変更はフォームから見えないため、フォームの input を拾う仕組み(ライブプレビューの自動更新など)へ伝える。
  // 値の書き戻しは送信前の triggerSave() が行うので、ここでは通知だけ。初期化時の SetContent は変更ではないので送らない
  function bindInputNotification(editor) {
    function dispatchToSource(event) {
      const el = editor.getElement();
      if (el && el.tagName === 'TEXTAREA') {
        el.dispatchEvent(event);
      }
    }

    function notifyInput() {
      dispatchToSource(new Event('input', {bubbles: true}));
    }

    // composition イベントも元の欄へ送り、フォーム側の変換中の判定を普通の欄と同じ処理にする
    function notifyComposition(type) {
      dispatchToSource(new CompositionEvent(type, {bubbles: true}));
    }

    // 変換中(compositionstart から compositionend まで)は input を送らず、確定(compositionend)で 1 回送る
    let composing = false;

    function notifyUnlessComposing() {
      if (!composing) {
        notifyInput();
      }
    }

    editor.on('compositionstart', function () {
      composing = true;
      notifyComposition('compositionstart');
    });
    editor.on('compositionend', function () {
      composing = false;
      notifyInput();
      notifyComposition('compositionend');
    });
    // 変換の途中で blur したら、compositionend が来なくても元の欄へ終わりを伝えて印を戻す
    editor.on('blur', function () {
      if (composing) {
        composing = false;
        notifyComposition('compositionend');
      }
    });
    editor.on('input change undo redo', notifyUnlessComposing);
    editor.on('init', function () {
      editor.on('SetContent', notifyUnlessComposing);
    });
  }

  function sortTokens(value) {
    return value.split(/\s+/).filter(function (token) {
      return token !== '';
    }).sort().join(' ');
  }

  // TinyMCE は rel の選択肢を並べ替えてからリンクの値と完全一致で比べる。
  // 一覧があるときだけ、読み込み時に本文の rel も同じ順にそろえる（最初の履歴より前なので未保存の印は立たない）
  function bindRelOrdering(editor) {
    editor.on('PreInit', function () {
      const list = editor.options.get('link_rel_list');
      if (!Array.isArray(list) || list.length === 0) {
        return;
      }
      editor.parser.addAttributeFilter('rel', function (nodes) {
        nodes.forEach(function (node) {
          if (node.name !== 'a') {
            return;
          }
          const rel = node.attr('rel');
          if (typeof rel === 'string' && rel !== '') {
            node.attr('rel', sortTokens(rel));
          }
        });
      });
    });
  }

  function asIs(value) {
    return value;
  }

  // 選択肢どうしを比べるときは、TinyMCE が新しいウィンドウのリンクに足す noopener を除く
  function relWithoutNoopener(value) {
    return sortTokens(value.split(/\s+/).filter(function (token) {
      return token !== 'noopener';
    }).join(' '));
  }

  // リンク・画像のダイアログの「クラス」「rel」は、一覧に無い値を保てず、そのまま保存すると属性を消す。
  // 開く前に、本文の該当する要素の値のうち一覧に無いものを選択肢に足す。
  // ダイアログが編集する要素は選び方で変わるため、本文のすべての該当する要素を見る
  // command はダイアログを開くコマンド、selector は値を集める要素
  const choiceLists = [
    {command: 'mceLink', selector: 'a[href]', option: 'link_class_list', attribute: 'class', normalize: asIs},
    {command: 'mceLink', selector: 'a[href]', option: 'link_rel_list', attribute: 'rel', normalize: relWithoutNoopener},
    {command: 'mceImage', selector: 'img', option: 'image_class_list', attribute: 'class', normalize: asIs}
  ];

  function bindChoiceLists(editor) {
    const bases = {};

    editor.on('BeforeExecCommand', function (e) {
      choiceLists.forEach(function (choices) {
        if (e.command !== choices.command) {
          return;
        }
        if (!bases[choices.option]) {
          const list = editor.options.get(choices.option);
          bases[choices.option] = Array.isArray(list) ? list : [];
        }
        const base = bases[choices.option];
        if (base.length === 0) {
          return;
        }
        const listed = new Set(base.map(function (item) {
          return item && typeof item.value === 'string' ? choices.normalize(item.value) : undefined;
        }));
        const extra = [];
        editor.dom.select(choices.selector).forEach(function (element) {
          const value = choices.normalize(element.getAttribute(choices.attribute) || '');
          if (value !== '' && !listed.has(value)) {
            listed.add(value);
            extra.push({title: value, value: value});
          }
        });
        editor.options.set(choices.option, base.concat(extra));
      });
    });
  }

  global.tinymce7EditorSetup = function (editor) {
    bindDocumentDirty(editor);
    bindInputNotification(editor);
    bindRelOrdering(editor);
    bindChoiceLists(editor);
  };
})(window);
