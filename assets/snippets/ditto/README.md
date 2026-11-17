# Ditto

Ditto は、条件に合うリソースを集めて一覧として出力するスニペット。新着情報・ブログ・索引などの一覧を、テンプレート（チャンク）で自由な形に整形できる。
HTML のほかに JSON・XML・RSS・Atom でも出力でき、ページ送り・絞り込み・タグ・日付での絞り込みを extender で足せる。

```
[[Ditto? &parents=`2` &display=`5` &tpl=`newsItem` &orderBy=`publishedon DESC`]]
```

この文書は、スニペットの呼び出しで指定できるパラメータと、Ditto がセットするプレースホルダーの一覧。
「既定値」が「言語ファイル」のものは、`lang/<language>.inc.php` の値を使う。

## 基本

| パラメータ | 用途 | とりうる値 | 既定値 |
| --- | --- | --- | --- |
| `ditto_base` | Ditto のファイルの置き場所 | サイトの中のディレクトリ（サイトのルートからのパス）。サイトの外を指したときは同梱の場所を使う | `assets/snippets/ditto/` |
| `id` | この呼び出しの名前。URL のパラメータとプレースホルダーの名前の前に `<id>_` が付き、1 ページに複数の Ditto を置ける。Reflect などほかのスニペットとの連携にも使う | 英字・数字・アンダースコア（大文字と小文字を区別する） | 空 |
| `language` | 既定の文言・エラーメッセージの言語 | `lang/` にある言語ファイルの名前 | 管理画面の言語（`manager_language`）。そのファイルが無ければ `english` |
| `format` | 出力の形式 | `html` / `json` / `xml` / `atom` / `rss` | `html` |
| `config` | 設定ファイルを読み込む | `default`、`configs/` にある設定の名前、`@FILE サイトのルートからのパス`（`.php` だけ） | `default` |
| `extenders` | 機能を足す extender を読み込む（関連: `config`） | カンマ区切り。`extenders/` にある名前（`request` / `tagging` / `dateFilter` / `summary`）、`@FILE サイトのルートからのパス`（`.php` だけ） | なし |

`configs/default.config.php` があれば、`config` の指定にかかわらず最初に読み込む。
`extenders` の名前は `extenders/<名前>.extender.inc.php` を読む。
`config`・`extenders` に `@CHUNK チャンク名` は使えない（チャンクを PHP として実行しない）。チャンクに書いていた PHP は `.php` のファイル（例: `assets/ditto/my.config.php`）に移し（先頭が `<?php` でなければ付ける）、`@FILE assets/ditto/my.config.php` で指定する。`@CHUNK` を渡すと、その指定を読まずにシステムログへ警告を残す。

## 対象のリソース

| パラメータ | 用途 | とりうる値 | 既定値 |
| --- | --- | --- | --- |
| `parents` | このリソースの子を `depth` の深さまで集める（関連: `documents`・`depth`） | リソース ID（カンマ区切り） | 今のリソース |
| `startID` | `parents` の別名 | 同上 | — |
| `documents` | 集めるリソースを直接指定する。指定すると `parents` は使わない | リソース ID（カンマ区切り） | なし |
| `depth` | 子をたどる深さ（関連: `seeThruUnpub`） | 1 以上の数。`0` は制限なし | `1` |
| `showPublishedOnly` | 公開中のリソースだけを対象にする | `1` 公開中だけ / `0` 非公開も含める | `1` |
| `showInMenuOnly` | メニューに表示するリソースだけを対象にする | `0` すべて / `1` 「メニューに表示」のものだけ | `0` |
| `hideFolders` | コンテナを結果から除く | `0` 残す / `1` 除く | `0` |
| `hidePrivate` | 閲覧者に権限の無いリソースを除く | `0` 除かない / `1` 除く | `1` |
| `seeThruUnpub` | 非公開のコンテナの下にあるリソースも集める（`depth` が 2 以上のときに意味がある） | `0` 集めない / `1` 集める | `1` |
| `where` | SQL の WHERE に足す条件。`AND` で区切った各条件の先頭に `sc.` が付く | リソースのフィールドだけを使った条件（TV は不可） | 空 |
| `queryLimit` | データベースから取り出す件数の上限（SQL の LIMIT）（関連: `where`） | 数。`0` は上限なし | 上限なし |
| `limit` | `queryLimit` の別名 | 同上 | — |
| `filter` | 条件に合うリソースを結果から除く。下の「filter の書き方」を参照 | `フィールド,値,モード` を `globalFilterDelimiter` で区切って並べる | 空 |
| `globalFilterDelimiter` | `filter` の中で条件どうしを区切る文字 | 条件の中で使わない文字 | `\|` |
| `localFilterDelimiter` | 1 つの条件の中でフィールド・値・モードを区切る文字 | 条件の中で使わない文字 | `,` |

### filter の書き方

`フィールド,値,モード` の形で書く（例: `` &filter=`template,3,1` `` はテンプレートが 3 のものだけを残す）。
モードは「その条件に当たるリソースを除く」向きで働く。

| モード | 別の書き方 | 除かれるリソース |
| --- | --- | --- |
| `1` | `!=` `<>` `ne` | 値と等しくないもの（等しいものが残る） |
| `2` | `==` `eq` | 値と等しいもの |
| `3` | `<` `lt` | 値より小さいもの |
| `4` | `>` `gt` | 値より大きいもの |
| `5` | `>=` `gte` `ge` | 値以上のもの |
| `6` | `<=` `lte` `le` | 値以下のもの |
| `7` | `=~` `find` `search` `strpos` | 値を含まないもの（含むものが残る） |
| `8` | `!~` `!=~` `!find` `!search` `!strpos` | 値を含むもの |
| `9` | — | 値を含まないもの（大文字と小文字を区別しない） |
| `10` | — | 値を含むもの（大文字と小文字を区別しない） |
| `11` | — | 先頭の 1 文字が値と違うもの（大文字・小文字は区別しない） |
| — | `regex` `preg` | 値の正規表現に合わないもの |

- モードを省いたとき、知らないモードのときは `9` として扱う。
- `フィールド,モード,値` の順でも書ける（3 番目がモードとして読めず、2 番目がモード（数字または別の書き方）のとき）。
- 値を `@EVAL` で始めると PHP のコードとして実行し、その戻り値を値に使う。グローバル設定でバインディングが有効（`enable_bindings`）のときだけ働く。
- 値の中の `[+プレースホルダー+]` は展開される。
- 日付のフィールド（`pub_date`・`unpub_date`・`published`・`createdon`・`editedon`・`publishedon`・`deletedon`）では、数字だけでない値を日付の文字列として読む。

## 件数・並び順・ページ送り

| パラメータ | 用途 | とりうる値 | 既定値 |
| --- | --- | --- | --- |
| `display` | 出力する件数（ページ送りでは 1 ページの件数）（関連: `queryLimit`・`total`） | 数 / `all` | `all` |
| `summarize` | `display` の別名 | 同上 | — |
| `total` | 対象にする件数の上限（関連: `display`・`queryLimit`） | 数 / `all` | `all` |
| `start` | 先頭から読み飛ばす件数 | 数 | `0` |
| `orderBy` | 並び順 | SQL の ORDER BY と同じ書き方（`フィールド 向き` をカンマ区切り）。向きを省くと最後のフィールドは DESC、それ以外は ASC になるので、フィールドごとに ASC か DESC を書く | `id ASC`（`documents` を指定したときは指定した順、`format=rss` のときは `<dateSource> DESC`） |
| `sortBy` / `sortDir` | 古い書き方の並び順。`orderBy` の別名ではなく、`orderBy` より先に効く | フィールド名 / `ASC`・`DESC` | 片方だけ指定したとき、もう片方は `createdon` / `DESC` |
| `randomize` | 並びをランダムにする | `0` オフ / `1` オン / フィールド名・TV 名（その値を重みにして選ぶ） | `0` |
| `paginate` | 結果を `display` 件ずつのページに分ける。全体の件数は `total` で絞る | `0` オフ / `1` オン | `0` |
| `paginateAlwaysShowLinks` | 前後のページが無いときも「前へ」「次へ」を出す（リンクなしのテンプレートを使う） | `0` オフ / `1` オン | `0` |
| `paginateSplitterCharacter` | ページ番号の間と、`[+splitter+]` に入る区切り | 任意の文字 | 言語ファイル（`button_splitter`） |

SQL で並べ替えるとき（TV、`pub_date`・`unpub_date`・`editedon`・`deletedon`・`publishedon` 以外）、値が同じリソースは、最初のフィールドの向きで id の順に並ぶ。

## テンプレート

テンプレートには、チャンク名のほかに `@CODE テンプレートの中身`・`@CHUNK チャンク名`・`@FILE ファイル`・`@INCLUDE PHP ファイル`・`@DOCUMENT リソース ID` を指定できる。

| パラメータ | 用途 | 既定値 |
| --- | --- | --- |
| `tpl` | 1 件ごとのテンプレート | 言語ファイル（`default_template`）。`html` 以外の形式は、その形式のテンプレート |
| `tplAlt` | 偶数番目（2・4・6 件目…）のテンプレート | `tpl` |
| `tplFirst` | 最初の 1 件のテンプレート | `tpl` |
| `tplLast` | 最後の 1 件のテンプレート | `tpl`（`format=json` のときは末尾のカンマが無いテンプレート） |
| `tplCurrentDocument` | 今表示しているリソースのテンプレート | `tpl` |
| `tplOuter`（`outerTpl`） | 一覧全体を包むテンプレート。`[+ditto+]`（`[+documents+]`・`[+docs+]` も同じ）が一覧に置き換わる。結果が 0 件のときは何も出力しない | なし |
| `header` / `footer` | 一覧の前後に出力する文字列 | 空（`html` 以外の形式は、その形式のヘッダー・フッター） |
| `noResults` | 結果が 0 件のときに出力する文字列 | 言語ファイル（`no_documents`）。`html` 以外の形式は空白 |

テンプレートの周りのパラメータ:

| パラメータ | 用途 | とりうる値 | 既定値 |
| --- | --- | --- | --- |
| `removeChunk` | 出力から取り除くチャンク（`{{チャンク名}}` と、そのチャンクの中身） | チャンク名（カンマ区切り） | なし |
| `hiddenFields` | テンプレートの解析では見つけられないフィールド（入れ子のプレースホルダーや `[*フィールド*]` で使うもの）を取り出しておく | フィールド名・TV 名（カンマ区切り） | なし |
| `dateSource` | `[+date+]` に使う日付のフィールド（関連: `dateFormat`）。値が空のリソースは `createdon` を使う | `createdon`・`pub_date`・`editedon` などの日付のフィールドや TV | `createdon`（`format=rss` のときは `publishedon`） |
| `dateFormat` | `[+date+]` の書式（関連: `dateSource`） | strftime の書式 | 言語ファイル（`dateFormat`） |
| `save` | Ditto のオブジェクトと結果をプレースホルダーに残し、ほかのスニペットから使えるようにする | `0` 残さない / `1` 残す（`ditto_resource` には表示した分より後ろの結果が入る） / `2` 残す（すべての結果） / `3` 残す（すべての結果）が、出力はしない | `0` |

### ページ送りのテンプレート

`paginate=1` のときに使う。

| パラメータ | 用途 | 使えるプレースホルダー | 既定値 |
| --- | --- | --- | --- |
| `tplPaginatePrevious` | 「前へ」のリンク | `[+url+]`、`[+lang:previous+]`（言語ファイルの `prev`） | `<a href="[+url+]" class="ditto_previous_link">[+lang:previous+]</a>` |
| `tplPaginateNext` | 「次へ」のリンク | `[+url+]`、`[+lang:next+]`（言語ファイルの `next`） | `<a href="[+url+]" class="ditto_next_link">[+lang:next+]</a>` |
| `tplPaginatePreviousOff` | 前のページが無いときの「前へ」（`paginateAlwaysShowLinks=1` のとき） | `[+lang:previous+]` | `<span class="ditto_previous_off ditto_off">[+lang:previous+]</span>` |
| `tplPaginateNextOff` | 次のページが無いときの「次へ」（`paginateAlwaysShowLinks=1` のとき） | `[+lang:next+]` | `<span class="ditto_next_off ditto_off">[+lang:next+]</span>` |
| `tplPaginatePage` | ページ番号のリンク | `[+url+]`、`[+page+]` | `<a class="ditto_page" href="[+url+]">[+page+]</a>` |
| `tplPaginateCurrentPage` | 今のページの番号 | `[+page+]` | `<span class="ditto_currentpage">[+page+]</span>` |

## プレースホルダー

### 1 件ごとのテンプレートの中

リソースのフィールド（`[+pagetitle+]`・`[+introtext+]` など）と TV（`[+TV名+]`）のほかに、次のものが使える。

| プレースホルダー | 中身 |
| --- | --- |
| `[+title+]` | `pagetitle` と同じ |
| `[+author+]` | 作成者のフルネーム（無ければユーザー名） |
| `[+date+]` | `dateSource` の日付を `dateFormat` で整形したもの |
| `[+url+]` | リソースの URL（サイトのトップは `site_url`） |
| `[+class+]` | 今表示しているリソースなら `active`、それ以外は空 |
| `[+ditto_iteration+]` | 何件目か（0 から） |

### ページ全体（Ditto の呼び出しの外で使う）

名前の前に `<id>_` が付く（`id=news` なら `[+news_next+]`）。

| プレースホルダー | 中身 |
| --- | --- |
| `[+item[x]+]` | x 件目（0 から）の出力 |
| `[+next+]` / `[+previous+]`（`[+prev+]`） | 「次へ」「前へ」のリンク |
| `[+pages+]` | ページ番号のリンクの並び |
| `[+splitter+]` | 前後の両方のページがあるときの区切り |
| `[+start+]` / `[+stop+]` | 今のページの最初・最後が何件目か |
| `[+urlStart+]` | URL の `start` の値 |
| `[+total+]` | 全体の件数 |
| `[+perPage+]` | 1 ページの件数 |
| `[+totalPages+]` / `[+currentPage+]` | 全体のページ数 / 今のページの番号 |
| `ditto_object` / `ditto_resource` | `save` を指定したときの Ditto のオブジェクトと結果の配列（スニペットから `getPlaceholder()` で受け取る） |

ページ送りのプレースホルダーは `paginate=1` のときにセットする。結果が 0 件のときは、リンクは空、数は `0` になる。
ページ送りの URL には `<id>_start=件数` が付く。

## 出力の形式（format）

`html` 以外の形式は、ヘッダー・フッター・1 件ごとのテンプレートの既定値を持ち、リソースのコンテントタイプを切り替える。
`rss` と `atom` は `summary` の extender も読み込む。

| パラメータ | 使う形式 | 用途 | 既定値 |
| --- | --- | --- | --- |
| `abbrLanguage` | json / xml / rss / atom | 言語の略称（2 文字） | 言語ファイル（`abbr_lang`） |
| `copyright` | json / xml / rss | 著作権の表示。xml・rss では XML の文字としてエスケープして出す（`&copy;`・`&amp;` のような書き済みの実体は、その文字として出る） | 言語ファイル（`default_copyright`） |
| `ttl` | json / xml / rss | キャッシュしてよい時間（分）。整数 | `120` |
| `charset` | xml / rss / atom | 文字コード | サイトの文字コード（`modx_charset`） |
| `xsl` | xml / rss | XSL スタイルシート（サイトのルートからのパス） | なし |

- `json`: URL に `<id>_jsonp=関数名` を付けると、出力全体を `関数名( … );` で包む（JSONP）。関数名は JavaScript の識別子（`a.b` の形も可）だけ。
- 1 件ごとのテンプレートでは、`[+json_フィールド+]`・`[+xml_フィールド+]`・`[+atom_escaped_フィールド+]` でその形式向けにエスケープした値が使える。`xml`・`atom` では、題名（`xml_pagetitle`・`atom_escaped_pagetitle`）と作成者（`xml_author`・`atom_author`）は地の文として扱い、書き済みの実体（`&amp;` など）を文字に戻してからエスケープする。ほかのフィールドは HTML を含みうるので、値をそのままエスケープする（読み取ると元の HTML に戻る）。`rss` は `[+rss_date+]`・`[+rss_pagetitle+]`・`[+rss_author+]`、`atom` は `[+atom_createdon+]`・`[+atom_editedon+]`・`[+atom_author+]` を持つ。

## extender

### request — URL からパラメータを変える

URL の `ditto_パラメータ名=値`（`id` を指定したときは `ditto_<id>_パラメータ名=値`）で、Ditto のパラメータを上書きする。
`ditto_filter`・`ditto_filter_2`・…（または `ditto_filter_1`・`ditto_filter_2`・…）と番号を続けて並べると、1 つの `filter` にまとめる。

| パラメータ | 用途 | とりうる値 | 既定値 |
| --- | --- | --- | --- |
| `stripTags` | URL の値から HTML タグを取り除く | `0` オフ / `1` オン | `1` |
| `good` | URL から変えてよいパラメータ | パラメータ名（カンマ区切り）。`filter` を含めると `filter_1`・`filter_2`・… も通る | `display,total,paginate,paginateAlwaysShowLinks,randomize,summarize,noResults,dateSource,dateFormat,filter` |
| `bad` | URL から変えさせないパラメータ | パラメータ名（カンマ区切り） | `seeThruUnpub,showInMenuOnly,showPublishedOnly,start,config,extenders,dittoID` |

- テンプレート（`tpl`・`tplAlt` など）は、`good` に挙げたときだけ URL から変えられる。
- SQL の条件・権限・対象のリソースに関わるもの（`where`・`hidePrivate`・`parents`・`startID`・`documents`・`depth`・`orderBy`・`queryLimit`・`limit`・`format`・`save` など）は、`good` に挙げても URL からは変えられない。
- 記号（`` ^ ` ~ ! / @ # $ % : ; ( ) { } & * = | ' + ``）を含む値は使わない。

### tagging — タグ

`tagData` を指定すると、`extenders` に書かなくても読み込む。URL の `<id>_tags=タグ` で絞り込む。

| パラメータ | 用途 | とりうる値 | 既定値 |
| --- | --- | --- | --- |
| `tagData` | タグを入れてあるフィールド | フィールド名・TV 名（カンマ区切り） | 空 |
| `tagDocumentID` | タグのリンクの飛び先 | タグを受け取る Ditto を置いたリソースの ID | 今のリソース |
| `tagDelimiter` | `tagData` の中でタグを区切る文字 | タグに含まれない文字（空にすると既定の値を使う） | 半角スペース |
| `tagDisplayDelimiter` | `[+tagLinks+]` でタグの間に入れる文字 | 任意の文字（空にすると区切りなしでつなぐ） | `tagDelimiter` |
| `tagMode` | 絞り込みの方法 | `onlyTags` どれかのタグを持つものだけ / `onlyAllTags` すべてのタグを持つものだけ / `removeTags` どれかのタグを持つものを除く / `removeAllTags` すべてのタグを持つものを除く | `onlyTags` |
| `tags` | 最初から絞り込んでおくタグ | タグ（`tagDelimiter` 区切り） | なし |
| `caseSensitive` | タグの一致と重複の判定で、大文字と小文字を区別する | `0` しない / `1` する | `0` |
| `tagSort` | タグを並べ替える | `0` オフ / `1` オン | `1` |
| `tagDisplayMode` | `[+tagLinks+]` の形 | `1` リンクを `tagDisplayDelimiter` でつなぐ / `2` ul・li のリスト | `1` |
| `tplTagLinks` | タグ 1 つ分のテンプレート。`[+url+]`・`[+tag+]` が使える | チャンク名・`@CODE`・`@FILE` | `<a href="[+url+]" class="ditto_tag" rel="tag">[+tag+]</a>`（xml・rss・atom は `<category>[+tag+]</category>`） |
| `tagCallback` | タグの取り出し方を差し替える関数。引数は、タグのフィールドの配列・リソースの配列・配列で返すかどうか。バインディングが有効（`enable_bindings`）のときだけ使う | 関数名 | なし |

プレースホルダー: 1 件ごとのテンプレートの `[+tagLinks+]`（そのリソースのタグのリンク）、ページ全体の `[+tagLinks+]`（絞り込みに使っているタグのリンク）と `[+tags+]`（絞り込みに使っているタグを `tagDelimiter` でつないだもの）。

### dateFilter — 日付で絞り込む

年・月・日を指定して、`dateSource` の日付がその範囲にあるリソースだけを残す。Reflect と組み合わせて使う。

| パラメータ | 用途 | とりうる値 | 既定値 |
| --- | --- | --- | --- |
| `dateFilterSource` | 年・月・日の受け取り方 | `get` URL の `<id>_year`・`<id>_month`・`<id>_day` / `params` スニペットの呼び出しの `year`・`month`・`day` / それ以外の文字列: その名前（前に `<id>_` が付く）のリクエストの値を日付として読む（日付として読めない値のときは、指定が無いときと同じ扱いで、`dateFilterDefault` に従う） | `get` |
| `dateSource` | 絞り込みに使う日付のフィールド（`[+date+]` と共通） | 日付のフィールド・TV | `createdon` |
| `dateFilterDefault` | 年・月・日の指定が無いときの扱い | `0` 絞り込まない / `1` 今年 / `2` 今月 / `3` 今日 | `0` |
| `year` / `month` / `day` | `dateFilterSource=params` のときの年・月（1〜12）・日 | 数 | なし |

プレースホルダー（ページ全体）: `[+year+]`、`[+month+]`（月の英語名）、`[+month_numeric+]`（月の数字）、`[+day+]`。指定のあるものだけセットする。

### summary — 要約

1 件ごとのテンプレートで `[+summary+]`（要約）と `[+link+]`（続きへのリンク）を使えるようにする。
要約は、本文に `truncAt` の区切りがあればその手前まで、無ければ `introtext`、それも無ければ本文を `truncLen` の長さで切ったもの。

| パラメータ | 用途 | とりうる値 | 既定値 |
| --- | --- | --- | --- |
| `trunc` | 本文を切り詰める | `0` オフ / `1` オン | `1` |
| `truncAt` | 本文を区切る目印 | 本文の中に置く文字列 | `<!-- splitter -->` |
| `truncLen` | 切り詰める長さ（文字数。全角も半角も 1 文字と数える） | `truncOffset` より大きい数 | `300` |
| `truncOffset` | `truncLen` の前後に許す幅 | `truncLen` より小さい数 | `30` |
| `truncText` | `[+link+]` の文言 | 文字列・HTML | `Read more...` |
| `tplTrunc` | `[+link+]` のテンプレート。`[+url+]`・`[+text+]`（`truncText`）が使える | チャンク名・`@CODE`・`@FILE` | `<a href="[+url+]" title="[+text+]">[+text+]</a>` |

## config・extender を書くとき

config と extender は、スニペットと同じ変数の中で動く PHP のファイル。パラメータと同じ名前の変数を読み書きできるほかに、次の配列へ足して機能を増やせる。

```php
// 読み込む extender を足す
$extenders[] = 'summary';

// 並び順を足す（custom を使うと、ほかの並び順は効かない）
$orderBy['parsed'][] = ['フィールド', 'ASC'];
$orderBy['custom'][] = ['使うフィールド', 'usort() に渡す関数'];

// 絞り込みを足す。関数はリソースの配列を受け取り、残すなら 1、除くなら 0 を返す
$filters['custom']['名前'] = ['使うフィールド', '関数'];
$filters['parsed'][] = ['名前' => ['source' => 'フィールド', 'value' => '値', 'mode' => 'モード']];

// 1 件ごとのテンプレートで使うプレースホルダーを足す。関数はリソースの配列を受け取り、値を返す
$placeholders['名前'] = ['使うフィールド（カンマ区切り）', '関数'];
```

関数の名前は、同じページで Ditto が 2 回呼ばれても重ねて定義されないように、`function_exists()` で確かめてから定義する。
