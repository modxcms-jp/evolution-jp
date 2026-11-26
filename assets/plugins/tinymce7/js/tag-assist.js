/*
 * TinyMCE 7 の本文で、タグ（{{chunk}}・[[snippet]]・[*field*]・[(setting)]）を書くのを助ける（管理画面だけ）。
 * - 補完: 書き始め（{{ など）を打つと名前の候補を出し、選ぶと閉じ記号まで入れる
 * - 色付け: 本文の中のタグに種類ごとの地の色を付け、無い名前・無効のチャンクは別の見せ方にする
 * 名前は tag-names.php から、エディターごとに 1 回だけ取る。
 * editor-setup.js が window.mceModxTagAssist(editor) を呼ぶ。
 */
(function (global) {
  'use strict';

  if (global.mceModxTagAssist) {
    return;
  }

  const TAG_KINDS = [
    {key: 'chunk', open: '{{', close: '}}'},
    {key: 'snippet', open: '[[', close: ']]'},
    {key: 'field', open: '[*', close: '*]'},
    {key: 'setting', open: '[(', close: ')]'}
  ];
  const MAX_RESULTS = 30;
  // 補完中の名前に使わない文字。打ったら補完を終える
  const NAME_STOP = /[\s{}\[\]()*!~+]/;

  // 本文の中のタグ。{{ のあとが空白のものは、本体がタグとして扱わない（mergeChunkContent）
  const TAG_PATTERNS = [
    {kind: 'chunk', pattern: /\{\{(?!\s)([^{}]+?)\}\}/g},
    {kind: 'snippet', pattern: /\[\[([^\[\]]+?)\]\]|\[!([^\[\]!]+?)!\]/g},
    {kind: 'field', pattern: /\[\*([^\[\]*]+?)\*\]/g},
    {kind: 'setting', pattern: /\[\(([^\[\]()]+?)\)\]/g}
  ];
  const HIGHLIGHT_STYLES = {
    chunk: 'background-color:rgba(37, 99, 235, 0.16);color:#1e40af;',
    snippet: 'background-color:rgba(234, 88, 12, 0.16);color:#9a3412;',
    field: 'background-color:rgba(22, 163, 74, 0.16);color:#166534;',
    setting: 'background-color:rgba(147, 51, 234, 0.14);color:#6b21a8;',
    unknown: 'background-color:rgba(220, 38, 38, 0.14);color:#b91c1c;text-decoration:underline wavy #dc2626;',
    disabled: 'background-color:rgba(107, 114, 128, 0.16);color:#6b7280;text-decoration:line-through;'
  };

  function createNameStore(url) {
    let promise = null;
    let data = null;

    function index(names) {
      const result = {};
      TAG_KINDS.forEach(function (kind) {
        const items = Array.isArray(names[kind.key]) ? names[kind.key] : [];
        const map = new Map();
        items.forEach(function (item) {
          map.set(item.name, item);
        });
        result[kind.key] = {items: items, map: map};
      });
      return result;
    }

    return {
      load: function () {
        if (!promise) {
          promise = fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}})
            .then(function (response) {
              return response.ok ? response.json() : {};
            })
            .catch(function () {
              return {};
            })
            .then(function (json) {
              data = {kinds: index(json.names || {}), labels: json.labels || {}};
              return data;
            });
        }
        return promise;
      },
      get: function () {
        return data;
      }
    };
  }

  // ---- 補完 ----

  function matchNames(items, query) {
    const needle = query.toLowerCase();
    const head = [];
    const rest = [];
    items.forEach(function (item) {
      const name = item.name.toLowerCase();
      if (name.indexOf(needle) === 0) {
        head.push(item);
      } else if (needle !== '' && name.indexOf(needle) > 0) {
        rest.push(item);
      }
    });
    return head.concat(rest);
  }

  // TinyMCE は、登録した順に書き始めを左へ探し（空白まで）、最初に見つかった書き始めで補完を決め、ほかの書き始めは試さない。
  // そのため「{{a}}文[(」では {{ に決まる。どの書き始めで決まっても、入力位置にいちばん近い書き始めで読み直す。
  // start は、書き始めから入力位置までの文字列（trigger + pattern）の中の、本当の書き始めの位置
  function parseInput(text) {
    let found = null;
    TAG_KINDS.forEach(function (kind) {
      const index = text.lastIndexOf(kind.open);
      if (index !== -1 && (!found || index > found.start)) {
        found = {kind: kind, start: index};
      }
    });
    if (!found) {
      return null;
    }
    const query = text.slice(found.start + found.kind.open.length);
    return NAME_STOP.test(query) ? null : {kind: found.kind, start: found.start, query: query};
  }

  // range の始まりを、range の文字列の offset 文字目へ進める（テキストノードをまたぐ）
  function moveRangeStart(doc, range, offset) {
    const walker = doc.createTreeWalker(range.commonAncestorContainer, NodeFilter.SHOW_TEXT);
    let node = walker.currentNode.nodeType === Node.TEXT_NODE ? walker.currentNode : walker.nextNode();
    let rest = offset;
    while (node) {
      if (range.intersectsNode(node)) {
        const from = node === range.startContainer ? range.startOffset : 0;
        const to = node === range.endContainer ? range.endOffset : node.nodeValue.length;
        if (rest <= to - from) {
          range.setStart(node, from + rest);
          return;
        }
        rest -= to - from;
      }
      node = walker.nextNode();
    }
  }

  function bindAutocomplete(editor, store) {
    TAG_KINDS.forEach(function (kind) {
      editor.ui.registry.addAutocompleter('modx_tag_' + kind.key, {
        trigger: kind.open,
        minChars: 0,
        columns: 1,
        maxResults: MAX_RESULTS,
        // 既定は空白か行頭の直後だけで開く。タグは文の途中にも書くので、どこでも開く。
        // 名前に使わない文字（空白・括弧・閉じ記号）を打ったら一致しないとして終え、次のタグで開けるようにする
        matches: function (rng, text, pattern) {
          return parseInput(kind.open + pattern) !== null;
        },
        fetch: function (pattern) {
          const input = parseInput(kind.open + pattern);
          if (!input) {
            return Promise.resolve([]);
          }
          return store.load().then(function (data) {
            const disabledLabel = data.labels.disabled || '';
            return matchNames(data.kinds[input.kind.key].items, input.query).slice(0, MAX_RESULTS).map(function (item) {
              return {
                type: 'autocompleteitem',
                value: item.name,
                text: item.name + (item.note ? ' — ' + item.note : '') + (item.disabled ? disabledLabel : '')
              };
            });
          });
        },
        onAction: function (api, rng, value) {
          api.hide();
          const input = parseInput(rng.toString());
          if (!input) {
            return;
          }
          const target = rng.cloneRange();
          moveRangeStart(editor.getDoc(), target, input.start);
          editor.selection.setRng(target);
          editor.insertContent(editor.dom.encode(input.kind.open + value + input.kind.close));
        }
      });
    });
  }

  // ---- 色付け ----

  // タグの中身から、本体が探す名前を取り出す（document.parser.class.inc.php の _split_snip_call・splitKeyAndFilter と同じ区切り）。
  // 本体の名前の表と照らせないもの（@FILE、[*a|b*] の候補、[*name@context*]）は null で、照らさない
  function tagName(kind, inner) {
    let name = inner;
    if (kind === 'chunk' || kind === 'snippet') {
      name = name.split(/[?&\s]/)[0];
    }
    if (kind === 'field' && name.charAt(0) === '#') {
      name = name.slice(1);
    }
    name = name.split(':')[0].trim();
    if (name === '' || name.charAt(0) === '@' || /[|@]/.test(name)) {
      return null;
    }
    return name;
  }

  function tagState(store, kind, inner) {
    const data = store ? store.get() : null;
    const name = tagName(kind, inner);
    if (!data || name === null) {
      return null;
    }
    const item = data.kinds[kind].map.get(name);
    if (!item) {
      return 'unknown';
    }
    return item.disabled ? 'disabled' : null;
  }

  // CSS Custom Highlight API で範囲に色を付けるだけなので、本文の HTML は変えない（保存する内容に要素が増えない）。
  // 使えないブラウザーでは色を付けない
  function bindHighlight(editor, store) {
    let timer = null;
    const names = Object.keys(HIGHLIGHT_STYLES);

    function refresh() {
      timer = null;
      const win = editor.getWin();
      const doc = editor.getDoc();
      const body = editor.getBody();
      if (!win || !doc || !body) {
        return;
      }
      const ranges = {};
      names.forEach(function (name) {
        ranges[name] = [];
      });
      const walker = doc.createTreeWalker(body, win.NodeFilter.SHOW_TEXT);
      let node = walker.nextNode();
      while (node) {
        const text = node.nodeValue;
        if (text.indexOf('{{') !== -1 || text.indexOf('[') !== -1) {
          TAG_PATTERNS.forEach(function (tag) {
            tag.pattern.lastIndex = 0;
            let match = tag.pattern.exec(text);
            while (match) {
              const range = doc.createRange();
              range.setStart(node, match.index);
              range.setEnd(node, match.index + match[0].length);
              ranges[tag.kind].push(range);
              const state = tagState(store, tag.kind, match[1] !== undefined ? match[1] : match[2]);
              if (state) {
                ranges[state].push(range);
              }
              match = tag.pattern.exec(text);
            }
          });
        }
        node = walker.nextNode();
      }
      names.forEach(function (name) {
        const highlight = new win.Highlight(...ranges[name]);
        // 無い名前・無効の見せ方を、種類の色より上に重ねる
        highlight.priority = HIGHLIGHT_STYLES[name].indexOf('text-decoration') !== -1 ? 1 : 0;
        win.CSS.highlights.set('modx-tag-' + name, highlight);
      });
    }

    function schedule() {
      if (timer === null) {
        timer = global.setTimeout(refresh, 150);
      }
    }

    editor.on('init', function () {
      const win = editor.getWin();
      if (!win || !win.CSS || !win.CSS.highlights || typeof win.Highlight !== 'function') {
        return;
      }
      editor.dom.addStyle(names.map(function (name) {
        return '::highlight(modx-tag-' + name + '){' + HIGHLIGHT_STYLES[name] + '}';
      }).join(''));
      refresh();
      editor.on('SetContent input Undo Redo ExecCommand compositionend', schedule);
      if (store) {
        store.load().then(schedule);
      }
    });
  }

  global.mceModxTagAssist = function (editor) {
    editor.options.register('modx_tag_names_url', {processor: 'string', default: ''});
    const url = editor.options.get('modx_tag_names_url');
    const store = url ? createNameStore(url) : null;
    if (store) {
      bindAutocomplete(editor, store);
    }
    bindHighlight(editor, store);
  };
})(window);
