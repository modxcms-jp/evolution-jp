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
  // 「リンクの rel」が空でも、本文の rel から選択肢を作る(bindChoiceLists の seedNone)ので、一覧の有無にかかわらず、
  // 読み込み時に本文の rel を同じ順にそろえる（最初の履歴より前なので未保存の印は立たない）。
  // 並べ替えで変わるのは、複数の値を持ち順が昇順でない rel だけ（意味は同じ）
  function bindRelOrdering(editor) {
    editor.on('PreInit', function () {
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
  // ダイアログは開くときに一覧を読む。ツールバー・メニュー・右クリックはコマンドを通さずに開くため、
  // 本文が変わるたびに、本文の該当する要素の値のうち一覧に無いものを選択肢に足して作り直しておく。
  // ダイアログが編集する要素は選び方で変わるため、本文のすべての該当する要素を見る
  // selector は値を集める要素。seedNone を true にした行は、元の一覧が空でも、本文に値があれば
  // 「None + その値」の一覧を作る（本文に値が無ければ空のまま。欄を出さない）
  const choiceLists = [
    {selector: 'a[href]', option: 'link_class_list', attribute: 'class', normalize: asIs},
    {selector: 'a[href]', option: 'link_rel_list', attribute: 'rel', normalize: relWithoutNoopener, seedNone: true},
    {selector: 'img', option: 'image_class_list', attribute: 'class', normalize: asIs}
  ];

  function bindChoiceLists(editor) {
    const bases = {};

    function refresh() {
      choiceLists.forEach(function (choices) {
        if (!bases[choices.option]) {
          const list = editor.options.get(choices.option);
          bases[choices.option] = Array.isArray(list) ? list : [];
        }
        const base = bases[choices.option];
        if (base.length === 0 && !choices.seedNone) {
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
        if (base.length === 0) {
          editor.options.set(choices.option, extra.length === 0 ? [] : [{title: 'None', value: ''}].concat(extra));
          return;
        }
        editor.options.set(choices.option, base.concat(extra));
      });
    }

    editor.on('init SetContent change undo redo', refresh);
    // 変化の通知が間に合わない操作の取りこぼしを防ぐ
    editor.on('BeforeExecCommand', function (e) {
      if (e.command === 'mceLink') {
        refresh();
      }
    });
  }

  // アップロードの権限が無く、貼り付け・ドロップした画像を受けないとき、画像が捨てられる理由を知らせる。
  // 文言（modx_upload_forbidden_message）は、権限が理由のときだけサーバーが渡す
  function bindUploadForbiddenNotice(editor) {
    editor.options.register('modx_upload_forbidden_message', {processor: 'string', default: ''});
    const message = editor.options.get('modx_upload_forbidden_message');
    if (!message) {
      return;
    }
    function hasImage(data) {
      return !!data && Array.prototype.some.call(data.files || [], function (file) {
        return /^image\//.test(file.type);
      });
    }
    function notify(e) {
      if (hasImage(e.clipboardData || e.dataTransfer)) {
        editor.notificationManager.open({text: message, type: 'warning'});
      }
    }
    editor.on('paste drop', notify);
  }

  // 保存すると本文から失われる・変わるものがあるとき、開いた時点で 1 回だけ知らせる（操作は止めず、閉じるまで残す）。
  // 元の欄(textarea)の値と、エディターが保存するときの値(getContent())を、実行も読み込みもされない文書(DOMParser)で比べ、
  // 要素と属性の数の差を調べる。設定ごとの条件は書かない（TinyMCE が残す設定なら差が出ない）。
  // 通知はエディターの上に置く帯で、文は textContent で入れる（本文の断片は混ぜず、件数と言語ファイルの言葉だけ）。
  // TinyMCE の通知は位置を計算して重ねるため、本文の枠のスクロールやタブの切り替えに追従しない。帯はページの流れの中にあるので、追従の処理は要らない
  const lossElements = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'form', 'noscript', 'link', 'meta', 'template', 'math'];

  function countMarkup(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const counts = {};
    const sandboxes = [];

    function add(key) {
      counts[key] = (counts[key] || 0) + 1;
    }

    doc.querySelectorAll(lossElements.join(',')).forEach(function (element) {
      add(element.localName);
      if (element.localName === 'iframe') {
        sandboxes.push(element.getAttribute('sandbox'));
      }
    });
    doc.querySelectorAll('*').forEach(function (element) {
      Array.prototype.forEach.call(element.attributes, function (attribute) {
        if (/^on/i.test(attribute.name)) {
          add('event');
        }
        if (/^\s*javascript:/i.test(attribute.value)) {
          add('javascript');
        }
      });
    });
    counts.sandboxes = sandboxes;
    return counts;
  }

  function showLossNotice(editor, sentences) {
    const band = document.createElement('div');
    band.className = 'tinymce7-loss-notice';
    band.setAttribute('role', 'status');
    sentences.forEach(function (sentence) {
      const line = document.createElement('p');
      line.textContent = sentence;
      band.appendChild(line);
    });
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'tinymce7-loss-notice__close';
    close.setAttribute('aria-label', editor.translate('Close'));
    close.textContent = '\u00d7';
    close.addEventListener('click', function () {
      band.remove();
    });
    band.appendChild(close);
    const container = editor.getContainer();
    container.parentNode.insertBefore(band, container);
    editor.on('remove', function () {
      band.remove();
    });
  }

  function bindLossNotice(editor) {
    editor.options.register('modx_loss_notice', {processor: 'object'});
    const labels = editor.options.get('modx_loss_notice');
    const element = editor.getElement();
    if (!labels || !element || element.tagName !== 'TEXTAREA') {
      return;
    }
    // TinyMCE が初期化で元の欄の値を取り込む前に控える
    const original = countMarkup(element.value);
    editor.on('init', function () {
      const saved = countMarkup(editor.getContent());
      const lost = [];
      lossElements.concat(['event', 'javascript']).forEach(function (key) {
        const diff = (original[key] || 0) - (saved[key] || 0);
        if (diff > 0) {
          lost.push(labels.item.replace('%s', labels.names[key] || key).replace('%d', diff));
        }
      });
      const sentences = [];
      if (lost.length > 0) {
        sentences.push(labels.lost.replace('%s', lost.join(labels.separator)));
      }
      // sandbox が付く・変わる（保存後に、元の iframe に無い sandbox の値が現れる。object が iframe に変わるときも含む）
      const before = original.sandboxes.slice();
      const added = saved.sandboxes.filter(function (value) {
        const index = before.indexOf(value);
        if (index === -1) {
          return value !== null;
        }
        before.splice(index, 1);
        return false;
      });
      if (added.length > 0) {
        sentences.push(labels.sandbox);
      }
      if (sentences.length === 0) {
        return;
      }
      sentences.push(labels.consult);
      showLossNotice(editor, sentences);
    });
  }

  // リソースの編集画面の本文（#content_body の中の欄）は、内容の量に合わせて高さを変える。
  // 下限は設定の height、上限はエディタ全体が管理画面の本文の枠（#mainPane）の見える高さに収まる高さ
  // （上のタブの行と、下の「使用エディター」の欄の分を空ける）。上限を超える分はエディタの中でスクロールする。
  // 右下のつまみで高さを変えたら、そのあとは変えない。全画面表示の間と、本文が隠れたタブにある間も変えない。
  // 高さは枠に直接付ける（つまみで変えるのと同じ）。測り直すのは本文の body の大きさが変わったとき
  // （内容の変更・画像の読み込み・タブの切り替え・窓の幅の変更）と、窓の大きさが変わったとき。枠を変えても body の大きさは変わらないので繰り返さない
  function fitContentHeight(editor) {
    const el = editor.getElement();
    if (!el || !el.closest('#content_body')) {
      return;
    }
    let resizedByUser = false;

    function maxHeight() {
      const pane = document.getElementById('mainPane');
      const tabRow = document.querySelector('#mainPane .tab-row');
      const visible = (pane ? pane.clientHeight : global.innerHeight) - (tabRow ? tabRow.offsetHeight : 0);
      return Math.floor(visible - 120);
    }

    function resize() {
      const container = editor.getContainer();
      const iframe = editor.iframeElement;
      const body = editor.getBody();
      if (resizedByUser || !container || !container.offsetParent || !iframe || !body
        || (editor.plugins.fullscreen && editor.plugins.fullscreen.isFullscreen())) {
        return;
      }
      const minHeight = parseInt(editor.options.get('height'), 10) || 0;
      const style = editor.getWin().getComputedStyle(body);
      const contentHeight = body.offsetHeight + parseFloat(style.marginTop) + parseFloat(style.marginBottom);
      const chromeHeight = container.offsetHeight - iframe.offsetHeight;
      const height = Math.max(Math.min(contentHeight + chromeHeight, maxHeight()), minHeight);
      if (height !== container.offsetHeight) {
        container.style.height = height + 'px';
      }
    }

    editor.on('init', function () {
      // つまみの操作（ドラッグと矢印キー）。ResizeEditor は全画面の切り替えでも起きるので使わない
      const container = editor.getContainer();
      function markResizedByUser(e) {
        // 要素でないもの（文字のノードなど）が target のときは、つまみの操作ではない
        if (typeof e.target.closest !== 'function') {
          return;
        }
        if (e.target.closest('.tox-statusbar__resize-handle') && (e.type === 'pointerdown' || (e.key || '').indexOf('Arrow') === 0)) {
          resizedByUser = true;
        }
      }
      container.addEventListener('pointerdown', markResizedByUser);
      container.addEventListener('keydown', markResizedByUser);
      const observer = new (editor.getWin().ResizeObserver)(resize);
      observer.observe(editor.getBody());
      // 窓の高さだけが変わったときは body の大きさが変わらない
      global.addEventListener('resize', resize);
      // シェルの画面遷移では editor.remove() で破棄される。窓のリスナーと監視が破棄したエディタを持ち続けないよう外す
      editor.on('remove', function () {
        global.removeEventListener('resize', resize);
        observer.disconnect();
      });
    });
  }

  // 設定「script と style」を「残す」にしたときだけ、EditorInitializer が TinyMCE の protect に足す。
  // protect の中身は escape() で退避されるので、MODX タグや日本語もそのまま残る。無害化（xss_sanitization）は切らない。
  // コメントの中に script・style があるときは、コメントごと保護する（左から順に一致するので、script の中の <!-- は script の一部になる）
  global.tinymce7KeepScriptsProtect = [
    /<script(?=[\s>\/])[\s\S]*?<\/script\s*>|<style(?=[\s>\/])[\s\S]*?<\/style\s*>|<!--(?=(?:(?!-->)[\s\S])*?<(?:script|style)(?=[\s>\/]))[\s\S]*?-->/gi
  ];

  // 保護した部分は <!--mce:protected …--> のコメントとして持たれ、取り出すときに元の HTML に戻る。
  // 貼り付け・ドロップした HTML にこのコメントがあると、外から script が入るので、「残す」のときは消す。
  // エディターの中での切り取り・貼り付けも同じ扱い（e.internal では分けない）
  function bindDropProtectedComment(editor) {
    const patterns = editor.options.get('protect') || [];
    if (patterns.indexOf(global.tinymce7KeepScriptsProtect[0]) === -1) {
      return;
    }
    editor.on('PastePostProcess', function (e) {
      const walker = e.node.ownerDocument.createTreeWalker(e.node, NodeFilter.SHOW_COMMENT);
      const found = [];
      while (walker.nextNode()) {
        if (walker.currentNode.nodeValue.indexOf('mce:protected ') === 0) {
          found.push(walker.currentNode);
        }
      }
      found.forEach(function (node) {
        node.parentNode.removeChild(node);
      });
    });
  }

  global.tinymce7EditorSetup = function (editor) {
    fitContentHeight(editor);
    bindUploadForbiddenNotice(editor);
    bindDocumentDirty(editor);
    bindInputNotification(editor);
    bindRelOrdering(editor);
    bindChoiceLists(editor);
    bindDropProtectedComment(editor);
    if (typeof global.mceModxImageEdit === 'function') {
      global.mceModxImageEdit(editor);
    }
    if (typeof global.mceModxPastedUpload === 'function') {
      global.mceModxPastedUpload(editor);
    }
    if (typeof global.mceModxTagAssist === 'function') {
      global.mceModxTagAssist(editor);
    }
    if (typeof global.mceModxLinkOpen === 'function') {
      global.mceModxLinkOpen(editor);
    }
    bindLossNotice(editor);
  };
})(window);
