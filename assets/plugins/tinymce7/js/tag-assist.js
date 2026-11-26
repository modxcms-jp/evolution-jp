/*
 * TinyMCE 7 の本文で、タグ（{{chunk}}・[[snippet]]・[*field*]・[(setting)]）を書くのを助ける（管理画面だけ）。
 * - 補完: 書き始め（{{ など）を打つと名前の候補を出し、選ぶと閉じ記号まで入れる
 * - 色付け: 本文の中のタグに種類ごとの地の色を付け、無い名前・無効のチャンクは別の見せ方にする
 * 名前は tag-names.php から取る（同じ画面のエディターで分け合う。nameStoreFor）。取れなかったときは照らさない。
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

  // 本文の中のタグ。本体がタグとして扱わないものには色を付けない:
  // {{ のあと・}} の前が半角スペースのチャンク（mergeChunkContent）、先頭が空白のスニペット（_get_snip_result）。
  // 古いブラウザーでも読めるように、後読みは使わない
  const TAG_PATTERNS = [
    {kind: 'chunk', pattern: /\{\{(?! )([^{}]*?[^{} ])\}\}/g},
    {kind: 'snippet', pattern: /\[\[(?!\s)([^\[\]]+?)\]\]|\[!(?!\s)([^\[\]!]+?)!\]/g},
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
  // 無い名前・無効の見せ方は、種類の色より上に重ねる
  const STATE_HIGHLIGHTS = ['unknown', 'disabled'];
  // 本体が名前の表とは別に値を置くリソース変数（mergeDocumentContent）
  const BUILTIN_FIELDS = ['phx', 'dummy'];

  // 同じ画面のエディター（リッチテキストのテンプレート変数など）は、まとめて初期化されるので 1 回の取得を分け合う。
  // 管理画面は画面を移ってもこのスクリプトを読み直さない（シェルの遷移も、document.open() での書き直しも）。
  // 分け合うのは作ってから短い間だけにし、画面ごとに取り直して、作ったばかりの要素を無い名前にしない
  const NAME_STORE_SHARE_MS = 5000;
  const nameStores = new Map();

  function nameStoreFor(url) {
    const now = Date.now();
    const entry = nameStores.get(url);
    if (entry && now - entry.createdAt < NAME_STORE_SHARE_MS) {
      return entry.store;
    }
    const store = createNameStore(url);
    nameStores.set(url, {store: store, createdAt: now});
    return store;
  }

  // 取得に失敗したあと、取り直すまでの間（ログインが切れているあいだ、打鍵のたびに取りに行かない）
  const NAME_RETRY_MS = 5000;

  function createNameStore(url) {
    let promise = null;
    let data = null;
    let failedAt = 0;
    const listeners = [];

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
        if (!promise && Date.now() - failedAt < NAME_RETRY_MS) {
          return Promise.resolve(null);
        }
        if (!promise) {
          // 取れなかったとき（ログインが切れた・エラー）は data を null のままにし、照らさない（全部を無い名前にしない）。
          // しばらくしてから補完を開いたときに取り直し、取れたら同じ一覧を使うエディターに知らせる（色付けを直す）
          promise = fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}})
            .then(function (response) {
              if (!response.ok) {
                throw new Error('tag-names: ' + response.status);
              }
              return response.json();
            })
            .then(function (json) {
              if (!json || typeof json.names !== 'object' || json.names === null) {
                throw new Error('tag-names: unexpected response');
              }
              data = {kinds: index(json.names), labels: json.labels || {}};
              listeners.forEach(function (listener) {
                listener();
              });
              return data;
            })
            .catch(function () {
              promise = null;
              failedAt = Date.now();
              return null;
            });
        }
        return promise;
      },
      get: function () {
        return data;
      },
      subscribe: function (listener) {
        listeners.push(listener);
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

  // 名前の残りに数える文字（引数・フィルターの記号 ` ' " = & ? : などで止める。外側のタグの閉じ記号を取り込まない）
  const NAME_REST = /^[^\s{}\[\]()*!~+`'"=&?:|@#]*/;
  // 名前は空白を含んでよい（{{my chunk}}）。改行を除く空白も名前の残りに数えたうえで、すぐあとが閉じ記号かを見る
  const NAME_REST_WITH_SPACES = /^[^\n{}\[\]()*!~+`'"=&?:|@#]*/;

  // 既にあるタグの名前の途中で選んだときに、入力位置のあとの名前の残りまでを置き換える範囲にし、閉じ記号を付けるかを返す。
  // 名前は空白を含んでよい（_getSplitPosition）ので、名前の残りは空白も含めて見る。
  // - 名前の残りのあとが閉じ記号: 閉じ記号まで置き換え、閉じ記号を付ける（{{bread_cr|umb}} → {{bread_crumb}}、{{my| chunk}} → {{my chunk}}）
  // - 名前の残りのあとが ?・&（前に空白があってもよい）: 名前の残りだけを置き換え、閉じ記号を付けない（[[Wayf|inder? &x=1]] → [[Wayfinder? &x=1]]）
  // - 空白を挟まずに : が続く: フィルターとして残す（{{bread_cr|umb:upper}} → {{bread_crumb:upper}}）。文中の「時刻: 」を名前に取り込まないよう、空白のあとの : は見ない
  // - それ以外（新しく書いている途中）: 置き換えを広げず、閉じ記号を付ける
  function extendRangeToName(range, kind) {
    const node = range.endContainer;
    if (node.nodeType !== Node.TEXT_NODE) {
      return true;
    }
    const after = node.nodeValue.slice(range.endOffset);
    const spaced = NAME_REST_WITH_SPACES.exec(after)[0];
    if (after.slice(spaced.length).indexOf(kind.close) === 0) {
      range.setEnd(node, range.endOffset + spaced.length + kind.close.length);
      return true;
    }
    const trimmed = spaced.replace(/\s+$/, '');
    if (/^\s*[?&]/.test(after.slice(trimmed.length))) {
      range.setEnd(node, range.endOffset + trimmed.length);
      return false;
    }
    const rest = NAME_REST.exec(after)[0];
    if (after.slice(rest.length).charAt(0) === ':') {
      range.setEnd(node, range.endOffset + rest.length);
      return false;
    }
    return true;
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
            if (!data) {
              return [];
            }
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
          const withClose = extendRangeToName(target, input.kind);
          editor.selection.setRng(target);
          editor.insertContent(editor.dom.encode(input.kind.open + value + (withClose ? input.kind.close : '')));
        }
      });
    });
  }

  // ---- 色付け ----

  // 名前と引数の境目。document.parser.class.inc.php の _getSplitPosition を写したもの（0 は境目にしない）。
  // 名前は空白を含んでよく、? ・改行・&（直前が空白ならその空白）で切る。フィルター（: のあと）では ? が無ければ空白でも切る
  function splitPosition(str) {
    let closeOpt = '';
    let maybePos = 0;
    let inFilter = false;
    const qpos = str.indexOf('?');
    let pos = 0;
    for (let i = 0; i < str.length; i++) {
      const c = str.charAt(i);
      const cc = str.substr(i, 2);
      if (!inFilter) {
        if (c === ':') {
          inFilter = true;
        } else if (c === '?') {
          pos = i;
        } else if (c === ' ') {
          maybePos = i;
        } else if (c === '&' && maybePos) {
          pos = maybePos;
        } else if (c === '&' && qpos <= 0) {
          pos = i;
        } else if (c === '\n') {
          pos = i;
        } else {
          pos = 0;
        }
      } else if (closeOpt && (cc === closeOpt || c === closeOpt)) {
        closeOpt = '';
      } else if (closeOpt) {
        continue;
      } else if (cc === "('") {
        closeOpt = "')";
      } else if (cc === '("') {
        closeOpt = '")';
      } else if (cc === '(`') {
        closeOpt = '`)';
      } else if (c === '(') {
        closeOpt = ')';
      } else if (c === '?') {
        pos = i;
      } else if (c === ' ' && qpos === -1) {
        pos = i;
      } else {
        pos = 0;
      }
      if (pos) {
        break;
      }
    }
    return pos;
  }

  // タグの中身から、本体が探す名前を取り出す（_split_snip_call・splitKeyAndFilter・mergeDocumentContent と同じ順）。
  // 名前の表と照らせないものは null で、照らさない:
  // @FILE などの @、プラグインが足す #name（チャンク・スニペット）、[[$_GET(x)]] のようなスーパーグローバル、
  // [*a|b*] の候補の並び、[*name@context*]、本体が置く [*phx*]・[*dummy*]
  function tagName(kind, inner) {
    let name = inner;
    if (kind === 'chunk' || kind === 'snippet') {
      const pos = splitPosition(name);
      name = pos ? name.slice(0, pos) : name;
      if (/^(#|\$_)/.test(name)) {
        return null;
      }
    }
    if (kind === 'field' && name.charAt(0) === '#') {
      name = name.slice(1);
    }
    name = name.split(':')[0].trim();
    if (name === '' || name.charAt(0) === '@' || /[|@]/.test(name)) {
      return null;
    }
    if (kind === 'field' && BUILTIN_FIELDS.indexOf(name) !== -1) {
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
        highlight.priority = STATE_HIGHLIGHTS.indexOf(name) !== -1 ? 1 : 0;
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
        store.subscribe(schedule);
        store.load();
      }
    });
  }

  global.mceModxTagAssist = function (editor) {
    editor.options.register('modx_tag_names_url', {processor: 'string', default: ''});
    const url = editor.options.get('modx_tag_names_url');
    const store = url ? nameStoreFor(url) : null;
    if (store) {
      bindAutocomplete(editor, store);
    }
    bindHighlight(editor, store);
  };
})(window);
