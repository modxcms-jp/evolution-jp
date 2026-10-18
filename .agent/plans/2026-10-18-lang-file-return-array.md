# ExecPlan: 管理画面の言語ファイルを配列を返す形にし、英語の値で補って読み込む（Issue #800）

## Purpose / Big Picture

管理画面の言語ファイル（`manager/includes/lang/english.inc.php`・`japanese-utf8.inc.php`）を、グローバル変数 `$_lang` に書き込む形から `return [...]` で配列を返す形に変える。読み込みは 1 つの関数にまとめ、英語を読んでから選んだ言語で上書きする。訳の抜けたキーが空のまま表示される不具合（#798 の形）を、仕組みとして起きないようにする。

## Progress

- [x] (2026-10-18) M1: 読み込み箇所の棚卸し。Issue の 5 か所に加え、`lang()`（`helpers.php`）と `get_lang_keys()`（設定画面）も言語ファイルを直接 include していた
- [x] (2026-10-18) M2: 言語ファイルを変換スクリプトで変換し、変換前後の配列が値・順序とも一致することを確認
- [x] (2026-10-18) M3: `getLexicon()`・`readLexiconFile()` を追加し、読み込み箇所をすべて置き換え
- [x] (2026-10-18) M4: Docker の検証環境で、英語・日本語の両方の画面を確認（変更前の HEAD とも比較）

## Surprises & Discoveries

- 2026-10-18: `accesscontrol-not-mgr.inc.php` の `include_once` は、`manager/index.php` の `loadLexicon()` で同じファイルが読み込み済みのため、もともと何もしていなかった。削除した。
- 2026-10-18: 旧 `get_lang_keys()` は `global $_lang` を宣言してから、すべての言語ファイルを順に include していた。そのため設定画面を開くと、グローバルの `$_lang` が最後に読んだ言語（`japanese-utf8`）の値で上書きされていた。新しい形ではファイル単体を読んで配列を返すだけなので、`$_lang` は変わらない。
- 2026-10-18: 重複キーが英語に 3 件（`disabled`・`edit_draft`・`view_child_resources_in_container`）、日本語に 2 件ある。配列リテラルでも後の値が勝つので、動作は変わらない。キーの整理は #797・#799 の範囲なので残した。
- 2026-10-18: 言語ファイルの `$modx_manager_charset = 'UTF-8'` が、`loadLexicon()` の既定値 `utf-8` を上書きしていた。実際に使われていたのは `UTF-8` なので、`loadLexicon()` で `UTF-8` に固定した。

## Decision Log

- 2026-10-18 / yamamoto: #797・#799 より先に着手する。後から触る作業は新しい形のファイルを編集すればよく、衝突したら変換スクリプトで作り直せる。
- 2026-10-18: 言語以外の値は、配列の `lang_attribute`（必須、`en`／`ja`）と `textdir`（任意、既定 `ltr`）のキーで持つ。`loadLexicon()` がそこからグローバルの `$modx_lang_attribute`・`$modx_textdir` を設定するので、それを使っている画面・プラグインは変えない。文字コードは `UTF-8` に固定し、ファイルには持たない。
- 2026-10-18: 古い形の言語ファイル（配列を返さない）は、`$_lang` を使い、`$modx_lang_attribute`・`$modx_textdir` があれば上の 2 つのキーにする。include は static なクロージャの中で行い、グローバル変数を書き換えない。
- 2026-10-18: `get_lang_keys()` は英語で補わない `readLexiconFile()` を使う。補うと、すべての言語が英語のキーを持つことになり、言語ごとの既定値の候補が正しく出ない。
- 2026-10-18: 言語の名前は `getLexicon()` で `^[\w\-]+$` だけを受け付け、それ以外は英語だけを返す。
- 2026-10-18: 変換スクリプトはリポジトリに入れず、この計画の Artifacts に載せる（変換後は使う場面が無い）。

## Outcomes & Retrospective

- 英語・日本語とも、ログイン画面・主な画面（a=2/27/76/75/17/86/35）・QM・DocManager・設定画面の a=118 が変更前と同じに動いた。PHP の Warning・Notice・Fatal は 0 件
- 日本語から `confirm` を消すと、ロール編集画面の確認ダイアログの見出しが英語の「Confirm」で補われた
- 英語の管理者のグローバル設定画面で、見出し・タブ・確認文が日本語に上書きされていたのが英語になった（変更前の HEAD と比べて確認）
- 範囲外で見つかったこと（変更前から同じ）：ログイン画面の `login.tpl` に `lang="ja"` が直接書かれている。設定画面の `tab1_doc_settings.inc.php`・`tab2_cache_settings.inc.php` のタブ名が日本語で直接書かれている

## Context and Orientation

- 読み込みの中心は `manager/includes/traits/document.parser.subparser.trait.php` の `loadLexicon()`。管理画面では `manager/index.php` から呼ばれる。DocumentParser の処理段階（`executeParser()` など）には関わらない
- 言語ファイルを読む箇所（変更前）
  - `loadLexicon()`：`global` を宣言して `include_once`
  - `manager/includes/accesscontrol-not-mgr.inc.php`：`include_once`（実際には何もしない）
  - DocManager（`assets/modules/docmanager/classes/docmanager.class.php`）：英語を読んでから、選んだ言語で上書き
  - QM（`assets/plugins/qm/inc/on_web_page_prerender.php`）：`include_once`
  - 設定画面の ajax（`manager/includes/mutate_settings.ajax.php` の `getStringFromLangFile()`）
  - `lang()`（`manager/includes/helpers.php`）：`$_lang` が空のとき include
  - `get_lang_keys()`（`manager/actions/tool/mutate_settings/functions.inc.php`）
- `$modx_lang_attribute` を使う箇所：`header.inc.php`・`resource_selector.static.php`・`browser.php`・`datepicker.inc.php`・`ddgmap.php`・`ddautofolders.php`・`frames/tree.php`・`frames/menu.php`。いずれもグローバル変数を読むだけなので変更しない
- キャッシュへの影響は無い（言語はリクエストごとに読む）

## Plan of Work

1. 変換スクリプトで 2 つの言語ファイルを変換し、変換前後の配列を比べる
2. トレイトに `getLexicon()`・`readLexiconFile()` を足し、`loadLexicon('manager')` をそれで書き直す
3. 読み込み箇所をすべて `getLexicon()`（`get_lang_keys()` だけ `readLexiconFile()`）に置き換える
4. 単体の確認スクリプトと検証環境で確かめる

## Concrete Steps

    php convert_lang.php manager/includes/lang/english.inc.php manager/includes/lang/japanese-utf8.inc.php
    php -l manager/includes/lang/english.inc.php

変換前後の比較（変換前に `$_lang` と `$modx_lang_attribute` を serialize して保存し、変換後の配列から `lang_attribute` を除いて `===` で比べる）:

    english: values=same order=same attr=same count=1265
    japanese-utf8: values=same order=same attr=same count=1265

## Validation and Acceptance

- 単体：トレイトを読み込んだ確認スクリプトで次の 16 項目がすべて OK
  - 英語・日本語の `lang_attribute`、日本語の値が使われる
  - 日本語から `confirm` を消すと英語の値（`Confirm`）で補われる。`readLexiconFile()` は補わない
  - 古い形のファイルの値・`lang_attribute`・`textdir` が読め、英語で補われる
  - 不正な名前・無い言語は英語だけ、省略時は設定の言語
  - `loadLexicon()` がグローバルの `$_lang`・`$modx_lang_attribute`・`$modx_textdir`・`$modx_manager_charset` を設定する
- 実機：英語と日本語の両方で、ログイン画面・管理画面の主な画面・QM・DocManager・設定画面の言語の切り替えが今まで通り動く

## Idempotence and Recovery

変換スクリプトは古い形のファイルにだけ効く。新しい形のファイルに流しても `$_lang[...]` の行が無いので、`return [` の見出しが付かず壊れる。作り直すときは、古い形のファイルを git から取り出してから流す。

## Artifacts and Notes

変換スクリプト（`convert_lang.php`）:

    <?php
    // 管理画面の言語ファイルを `$_lang[...] = ...;` の形から `return [...];` の形に変換する
    // 使い方: php convert_lang.php <言語ファイル>...
    foreach (array_slice($argv, 1) as $file) {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $out = [];
        $attr = null;
        $textdir = null;
        $inBody = false;
        foreach ($lines as $line) {
            if (preg_match('@^\s*\$modx_lang_attribute\s*=\s*\'([^\']*)\'@', $line, $m)) {
                $attr = $m[1];
                continue;
            }
            if (preg_match('@^\s*(//)?\s*\$modx_textdir\s*=\s*\'([^\']*)\'@', $line, $m)) {
                $textdir = [$m[1] === '//', $m[2]];
                continue;
            }
            if (preg_match('@^\s*\$modx_manager_charset\s*=@', $line)) {
                continue;
            }
            if (preg_match('@^\$_lang\[("[^"]+"|\'[^\']+\')\] = (.*);(\s*//.*)?\s*$@', $line, $m)) {
                if (!$inBody) {
                    $out = array_merge($out, header_lines($attr, $textdir));
                    $inBody = true;
                }
                $out[] = "    {$m[1]} => {$m[2]}," . ($m[3] ?? '');
                continue;
            }
            if (!$inBody) {
                $out[] = $line;
                continue;
            }
            $out[] = trim($line) === '' ? '' : '    ' . ltrim($line);
        }
        while (end($out) === '') {
            array_pop($out);
        }
        $out[] = '];';
        file_put_contents($file, implode("\n", $out) . "\n");
    }

    function header_lines($attr, $textdir)
    {
        $lines = [];
        $lines[] = 'return [';
        $lines[] = "    // HTML と XML の lang 属性（ISO 639-1）";
        $lines[] = "    'lang_attribute' => '" . ($attr ?: 'en') . "',";
        if ($textdir) {
            [$commented, $dir] = $textdir;
            $lines[] = '    ' . ($commented ? '// ' : '') . "'textdir' => '{$dir}'," . ($commented ? ' // 右から左に書く言語では、この行を有効にする' : '');
        }
        $lines[] = '';
        return $lines;
    }

## Interfaces and Dependencies

- `DocumentParser::getLexicon(?string $langname = null): array` … 英語を読んでから指定した言語で上書きした配列
- `DocumentParser::readLexiconFile(string $file_path): array` … 1 ファイルの配列（補わない）
- 本体の外のコードが言語ファイルを直接 `include` して `$_lang` を使っていると、値が入らなくなる。`evo()->getLexicon()` を使うよう PR とリリース記事の候補に書く
