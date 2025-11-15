# フロントエンドモダン化計画

## 概要

インストーラのフロントエンド部分をモダンなVanilla JavaScriptに移行し、jQueryへの依存を完全に廃止します。

## 背景と目的

### 現在の問題

1. **jQueryへの依存**
   - 外部ライブラリへの不要な依存
   - バンドルサイズの増加
   - モダンブラウザではネイティブAPIで十分

2. **パフォーマンス**
   - jQueryのオーバーヘッド
   - 不要な互換性レイヤー

3. **保守性**
   - 古い記法の使用
   - モダンJavaScriptの機能が使えない

### 目標

- ✅ jQueryの完全廃止
- ✅ Vanilla JavaScript（ES6+）への移行
- ✅ バンドルサイズの削減
- ✅ パフォーマンスの向上
- ✅ モダンな開発体験

## 現在のjQuery使用状況

### 使用箇所の分析

```bash
# 検出されたjQuery使用箇所
install/actions/summary.php        # フォーム送信ハンドラ
install/connection.servertest.php  # セレクトボックス動的生成
install/actions/install.php        # UIインタラクション
```

### 主な使用パターン

1. **イベントハンドリング**
```javascript
// 既存（jQuery）
jQuery('a.prev').click(function () {
    jQuery('#install input[name=action]').val('options');
    jQuery('#install').submit();
});
```

2. **DOM操作**
```javascript
// 既存（jQuery）
jQuery.each(characters, function (value, name) {
    opt = jQuery('<option>')
        .val(value)
        .text(name)
        .prop('selected', isSelected);
    sel.append(opt);
});
```

3. **要素選択と属性操作**
```javascript
// 既存（jQuery）
jQuery('#collation').find('option').remove();
jQuery('#install input[name=action]').val('options');
```

## 移行計画

### Phase 5での実装（Controller/View分離と同時）

フロントエンドモダン化はPhase 5で実装します。

### 移行パターン

#### 1. イベントハンドリング

```javascript
// Before (jQuery)
jQuery('a.prev').click(function () {
    jQuery('#install input[name=action]').val('options');
    jQuery('#install').submit();
});

// After (Vanilla JS)
document.querySelector('a.prev').addEventListener('click', () => {
    document.querySelector('#install input[name=action]').value = 'options';
    document.querySelector('#install').submit();
});
```

#### 2. DOM操作

```javascript
// Before (jQuery)
jQuery.each(characters, function (value, name) {
    opt = jQuery('<option>')
        .val(value)
        .text(name)
        .prop('selected', isSelected);
    sel.append(opt);
});

// After (Vanilla JS)
Object.entries(characters).forEach(([value, name]) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = name;
    option.selected = isSelected;
    sel.appendChild(option);
});
```

#### 3. 要素選択

```javascript
// Before (jQuery)
jQuery('#collation').find('option').remove();

// After (Vanilla JS)
const select = document.querySelector('#collation');
select.querySelectorAll('option').forEach(opt => opt.remove());
// または
select.innerHTML = '';
```

#### 4. AJAX通信（必要な場合）

```javascript
// Before (jQuery)
jQuery.ajax({
    url: '/api/endpoint',
    method: 'POST',
    data: {key: 'value'},
    success: function(data) {
        console.log(data);
    }
});

// After (Vanilla JS - Fetch API)
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({key: 'value'})
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

## モダンJavaScript設計

### ディレクトリ構造

```
install/public/js/
├── installer.js           # メインエントリーポイント
├── modules/
│   ├── formHandler.js     # フォーム処理
│   ├── validation.js      # クライアント側バリデーション
│   ├── collation.js       # 文字コード選択
│   └── progressBar.js     # 進捗バー（将来的に）
└── utils/
    ├── dom.js             # DOM操作ヘルパー
    └── events.js          # イベント処理ヘルパー
```

### モジュール設計

#### 1. formHandler.js

```javascript
/**
 * Form Handler Module
 *
 * フォーム送信とナビゲーションを管理
 */
export class FormHandler {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        this.actionInput = this.form.querySelector('input[name=action]');
        this.init();
    }

    init() {
        this.attachEventListeners();
    }

    attachEventListeners() {
        // 前へボタン
        document.querySelector('a.prev')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.setAction('options');
            this.submit();
        });

        // 次へボタン
        document.querySelector('a.next')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.submit();
        });
    }

    setAction(action) {
        this.actionInput.value = action;
    }

    submit() {
        this.form.submit();
    }
}
```

#### 2. collation.js

```javascript
/**
 * Collation Selector Module
 *
 * データベース文字コード選択UI
 */
export class CollationSelector {
    constructor(selectSelector, collations) {
        this.select = document.querySelector(selectSelector);
        this.collations = collations;
        this.init();
    }

    init() {
        this.populateOptions();
    }

    populateOptions() {
        // 既存のoptionをクリア
        this.select.innerHTML = '';

        // 新しいoptionを追加
        Object.entries(this.collations).forEach(([value, name]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = name;

            // デフォルト選択
            if (value === 'utf8mb4_general_ci') {
                option.selected = true;
            }

            this.select.appendChild(option);
        });
    }

    getValue() {
        return this.select.value;
    }

    setValue(value) {
        this.select.value = value;
    }
}
```

#### 3. installer.js（メインエントリーポイント）

```javascript
/**
 * Installer Main Module
 */
import { FormHandler } from './modules/formHandler.js';
import { CollationSelector } from './modules/collation.js';

// DOMContentLoaded後に初期化
document.addEventListener('DOMContentLoaded', () => {
    // フォームハンドラ初期化
    if (document.querySelector('#install')) {
        new FormHandler('#install');
    }

    // Collation選択初期化（グローバル変数から取得）
    if (window.collationData && document.querySelector('#collation')) {
        new CollationSelector('#collation', window.collationData);
    }
});
```

### ヘルパーユーティリティ

#### utils/dom.js

```javascript
/**
 * DOM Utilities
 *
 * よく使うDOM操作のヘルパー関数
 */

/**
 * 要素を選択（単一）
 */
export const $ = (selector, context = document) => {
    return context.querySelector(selector);
};

/**
 * 要素を選択（複数）
 */
export const $$ = (selector, context = document) => {
    return Array.from(context.querySelectorAll(selector));
};

/**
 * 要素を作成
 */
export const createElement = (tag, attributes = {}, children = []) => {
    const element = document.createElement(tag);

    // 属性設定
    Object.entries(attributes).forEach(([key, value]) => {
        if (key === 'class') {
            element.className = value;
        } else if (key === 'text') {
            element.textContent = value;
        } else {
            element.setAttribute(key, value);
        }
    });

    // 子要素追加
    children.forEach(child => {
        if (typeof child === 'string') {
            element.appendChild(document.createTextNode(child));
        } else {
            element.appendChild(child);
        }
    });

    return element;
};

/**
 * 要素を削除
 */
export const remove = (selector) => {
    $$(selector).forEach(el => el.remove());
};

/**
 * クラス操作
 */
export const toggleClass = (element, className) => {
    element.classList.toggle(className);
};

export const addClass = (element, className) => {
    element.classList.add(className);
};

export const removeClass = (element, className) => {
    element.classList.remove(className);
};
```

#### utils/events.js

```javascript
/**
 * Event Utilities
 *
 * イベント処理のヘルパー関数
 */

/**
 * イベントリスナー追加（委譲対応）
 */
export const on = (selector, event, handler, useCapture = false) => {
    if (typeof selector === 'string') {
        // セレクタ文字列の場合は委譲
        document.addEventListener(event, (e) => {
            if (e.target.matches(selector)) {
                handler.call(e.target, e);
            }
        }, useCapture);
    } else {
        // 要素の場合は直接追加
        selector.addEventListener(event, handler, useCapture);
    }
};

/**
 * DOMContentLoaded待機
 */
export const ready = (callback) => {
    if (document.readyState !== 'loading') {
        callback();
    } else {
        document.addEventListener('DOMContentLoaded', callback);
    }
};

/**
 * カスタムイベント発火
 */
export const trigger = (element, eventName, detail = {}) => {
    const event = new CustomEvent(eventName, {
        detail,
        bubbles: true,
        cancelable: true
    });
    element.dispatchEvent(event);
};
```

## ブラウザ互換性

### ターゲットブラウザ

Evolution CMSインストーラはモダンブラウザをターゲットとします：

- Chrome/Edge: 最新版
- Firefox: 最新版
- Safari: 最新版

### 使用するモダンAPI

以下のモダンAPIを使用します（全てターゲットブラウザでサポート済み）：

- ✅ `querySelector` / `querySelectorAll`
- ✅ `addEventListener`
- ✅ `classList` API
- ✅ `fetch` API
- ✅ ES6+ (arrow functions, const/let, template literals, modules)
- ✅ `Promise` / `async/await`
- ✅ `CustomEvent`

### ポリフィル不要

ターゲットブラウザが限定されているため、ポリフィルは不要です。

## パフォーマンスの改善

### バンドルサイズ削減

```
Before: ~85KB (jQuery 3.x minified + gzipped)
After:  ~5-10KB (カスタムJavaScript minified + gzipped)

削減率: 約88-94%
```

### ロード時間短縮

- jQueryのダウンロード不要
- パース時間の削減
- 初期化の高速化

## 実装フェーズ

### Phase 5-1: 基盤整備

1. ディレクトリ構造作成
2. ビルド環境セットアップ（必要に応じて）
3. ヘルパーユーティリティ実装

### Phase 5-2: 既存機能の移行

1. `actions/summary.php` のjQuery → Vanilla JS
2. `connection.servertest.php` のjQuery → Vanilla JS
3. `actions/install.php` のjQuery → Vanilla JS

### Phase 5-3: テストと最適化

1. ブラウザ互換性テスト
2. パフォーマンステスト
3. コードレビューと最適化

### Phase 5-4: jQuery削除

1. jQueryの参照を全て削除
2. CDNリンク削除
3. ドキュメント更新

## 移行チェックリスト

- [ ] ディレクトリ構造作成 (`public/js/`)
- [ ] ヘルパーユーティリティ実装 (`utils/`)
- [ ] FormHandlerモジュール実装
- [ ] CollationSelectorモジュール実装
- [ ] summary.php のjQuery削除
- [ ] connection.servertest.php のjQuery削除
- [ ] install.php のjQuery削除
- [ ] 全ページでの動作確認
- [ ] ブラウザ互換性テスト
- [ ] パフォーマンステスト
- [ ] jQueryファイル・参照の完全削除
- [ ] ドキュメント更新

## コーディング規約

### モダンJavaScript

```javascript
// strict mode使用
'use strict';

// const/let使用（var禁止）
const element = document.querySelector('#id');
let counter = 0;

// アロー関数優先
const handleClick = (e) => {
    console.log(e.target);
};

// テンプレートリテラル使用
const message = `Hello, ${name}!`;

// 分割代入
const { value, name } = formData;

// スプレッド演算子
const newArray = [...oldArray, newItem];
```

### 命名規則

- クラス名: PascalCase (`FormHandler`)
- 関数/変数: camelCase (`handleClick`)
- 定数: UPPER_SNAKE_CASE (`MAX_RETRIES`)
- プライベートメソッド: `_methodName`

### JSDoc

```javascript
/**
 * フォームを送信する
 *
 * @param {string} action - アクション名
 * @returns {void}
 */
submit(action) {
    // ...
}
```

## テスト

### 手動テスト

各ブラウザでの動作確認：

1. フォーム送信（前へ/次へ）
2. Collation選択
3. バリデーション表示
4. エラーハンドリング

### 自動テスト（将来的に）

Phase 5完了後、必要に応じてJestなどでユニットテストを追加。

## 参考リソース

### ドキュメント

- [MDN Web Docs - JavaScript](https://developer.mozilla.org/ja/docs/Web/JavaScript)
- [You Might Not Need jQuery](https://youmightnotneedjquery.com/)
- [Vanilla JS Toolkit](https://vanillajstoolkit.com/)

### 比較表

| 機能 | jQuery | Vanilla JS |
|------|--------|------------|
| 要素選択 | `$('#id')` | `document.querySelector('#id')` |
| クラス追加 | `$(el).addClass('class')` | `el.classList.add('class')` |
| イベント | `$(el).click(fn)` | `el.addEventListener('click', fn)` |
| AJAX | `$.ajax()` | `fetch()` |
| each | `$.each()` | `Array.forEach()` |

## まとめ

jQuery廃止により、以下のメリットを得られます：

1. **パフォーマンス向上** - バンドルサイズ90%削減
2. **保守性向上** - モダンなJavaScript記法
3. **依存削減** - 外部ライブラリ不要
4. **学習コスト低減** - 標準APIの使用

Phase 5でフロントエンド全体をモダン化し、クリーンで高速なインストーラを実現します。
