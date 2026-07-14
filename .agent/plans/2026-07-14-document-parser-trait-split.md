# ExecPlan: DocumentParser の責務別トレイト分割

## Purpose / Big Picture

9,176行に肥大化したパーサー本体2ファイル（`document.parser.class.inc.php` 6,320行 + `document.parser.subparser.trait.php` 2,856行）を責務別のトレイト11本へ機械的に再編し、変更対象の特定・レビュー・AI実装の精度を上げる。外部から見た `$modx` のAPI（メソッド名・シグネチャ・挙動）は一切変えない。

## Progress

- [ ] (2026-07-14) M0: メソッド棚卸しベースラインの作成（コード変更なし）
- [ ] (2026-07-14) M1: `tv` トレイト分離（TVコマンド・フォーム描画・atBind）
- [ ] (2026-07-14) M2: `log-error` トレイト分離（logEvent・messageQuit・sendmail 等）
- [ ] (2026-07-14) M3: `utility` / `misc` 受け皿トレイト分離
- [ ] (2026-07-14) M4: `url-alias` トレイト分離
- [ ] (2026-07-14) M5: `auth-user` トレイト分離
- [ ] (2026-07-14) M6: `cache-config` トレイト分離
- [ ] (2026-07-14) M7: `document-tree` トレイト分離
- [ ] (2026-07-14) M8: `element-exec` トレイト分離
- [ ] (2026-07-14) M9: `tag-parse` トレイト分離
- [ ] (2026-07-14) M10: `request-response` トレイト分離 + subparser トレイト廃止

## Surprises & Discoveries

- 計画時点の調査より: 現行の `DocumentParserSubParserTrait` は「責務」ではなく「使用頻度が低い関数」という基準で切り離された歴史的経緯を持つ（コミット 211420ad9）。責務が混在しているのは必然であり、本計画で解消する。
- レビュー指摘で判明: `document.parser.subparser.trait.php` の末尾（2848–2856行）に `@deprecated` 注記付きの `SubParser` クラスが定義されており、`DocumentParserSubParserTrait` を `use` している。トレイト廃止時にファイルごと削除すると、レガシー連携が `new SubParser()` した場合にクラス未定義エラーになる。リポジトリ内に呼び出し箇所は無い（grep確認済み）が、`loadExtension('subparser')` の互換シムを残す方針と整合させるため、この後方互換クラスも別の形で維持する。
- PHPのトレイトはコンパイル時にクラスへ平坦化されるため、トレイトAの private メソッドをトレイトBやクラス本体から呼べる。よって可視性の変更なしに純粋なメソッド移動だけで分割できる。
- `evo` CLI の bootstrap（`manager/includes/cli/bootstrap.php` 69–70行）が `new DocumentParser` を実行するため、トレイトのメソッド名衝突（クラス合成時のFatal Error）を CLI 起動だけで検出できる。

## Decision Log

- 2026-07-14 (yamamoto/Claude): クラス分解ではなくトレイト分割を採用。`$modx` の public メソッドは manager/actions・プラグイン・モジュール等から広く直接呼ばれ（例: `renderFormElement` は5箇所以上）、約100個の public プロパティを全メソッド群が共有するため、別クラスへの抽出は互換性リスクが大きい。トレイトなら同一オブジェクト上の状態共有を保ったまま物理ファイルだけ分けられる。
- 2026-07-14 (yamamoto/Claude): 責務トレイト9本 + 受け皿2本（`utility` = 薄い汎用ヘルパー、`misc` = 分類不能なレガシー）の計11ファイル構成とする。`misc` 所属メソッドは将来の deprecation 候補として明示する。
- 2026-07-14 (yamamoto/Claude): レガシー `SubParser` クラス（`@deprecated`、`document.parser.subparser.trait.php` 末尾で定義）は、`loadExtension('subparser')` 互換シムと同じ理由で維持する。ただし実体を新規ファイル `manager/includes/traits/document.parser.subparser.legacy.php` に移し、新設11トレイトすべてを `use` する形で再定義する（旧トレイトが持っていたメソッド集合は今や11トレイトに分散しているため、全`use`が最も確実にBCを保つ。深追いした最小集合の再現は、既に呼び出し箇所ゼロの deprecated コードに対しては過剰投資と判断）。
- 2026-07-14 (yamamoto/Claude): `DocumentParserSubParserTrait` は全メソッド移動後に廃止（ファイル削除 + `use` 文と `require_once` の除去）。ただし `loadExtension('subparser')` の互換シム（`document.parser.class.inc.php` 内の `case 'subparser': return true;`）はレガシープラグイン互換のため残す。トレイト名 `DocumentParserSubParserTrait` への参照はリポジトリ内に本体クラス以外存在しないことを確認済み。
- 2026-07-14 (yamamoto/Claude): 帰属判定ルールを次のとおり固定する。(1) public メソッドは後述のマッピング表に従う。(2) private ヘルパーは唯一の呼び出し元と同じトレイトに置く。(3) 複数トレイトから呼ばれる private ヘルパーは `utility` へ移す。ルール適用でマッピング表と異なる帰属になった場合は本 Decision Log に追記する。
- 2026-07-14 (yamamoto/Claude): 着手順は「独立性が高く依存が少ない順」とし、本丸の `tag-parse` / `request-response` を最後に回す。各マイルストーン = 1コミットで、失敗時の切り戻しを容易にする。

## Outcomes & Retrospective

実装後に記載。

## Context and Orientation

対象はEvolution CMS JP Editionのフロントエンド/管理画面共通のコアクラス `DocumentParser`（グローバル変数 `$modx` としてスニペット・プラグイン・管理画面コードから参照されるオブジェクト）。

- `manager/includes/document.parser.class.inc.php` — クラス本体（6,320行）。プロパティ宣言、マジックメソッド（`__get` / `__call`）、`__construct`、`loadExtension` に加え、リクエスト処理からユーティリティまで約190メソッドが同居。
- `manager/includes/traits/document.parser.subparser.trait.php` — 既存トレイト（2,856行、約80メソッド）。本計画で解体・廃止する。
- `manager/includes/cli/bootstrap.php` — `evo` CLI の起動処理。`DocumentParser` を require して `new` するため合成チェックに使える。

本計画が触れる範囲: `executeParser` / `parseDocumentSource` / `prepareResponse` / `postProcess` を含む、ドキュメント処理フェーズ全体のメソッドが移動対象になる。ただし全マイルストーンともファイルを跨いだ**メソッドの移動のみ**であり、呼び出し順・ロジック・シグネチャ・可視性は変更しない。

用語:

- トレイト (trait): PHPの言語機能。クラスにメソッド群を「コピーして混ぜ込む」仕組みで、`use トレイト名;` と書くとそのクラス自身のメソッドとして振る舞う。多重継承の代替。
- スニペット/チャンク/TV: Evolution CMSのコンテンツ部品。スニペット=PHPコード片、チャンク=HTML断片、TV(テンプレート変数)=ドキュメントに付く追加フィールド。
- サブパーサ (subparser): 過去に「使用頻度の低い関数」を分離した既存トレイトの通称。本計画で廃止される。

前提条件: PHPがホストにあれば構文チェックと合成チェックは可能（確認済み: PHP 8.3）。DBを伴う実機確認は `docker compose exec app php evo ...` で行う（`AGENTS.md` 106行参照。ホスト直実行は mysqli 不在で失敗する）。テストスイートとオートローダーは存在しないため、検証は本計画のコマンド群で担保する。

## Plan of Work

方針は「振る舞い変更ゼロの機械的メソッド移動」。各マイルストーンで新しいトレイトファイルを1〜2本作り、マッピング表に列挙したメソッドを本体クラスと既存 subparser トレイトから**そのまま切り取って**貼り付ける。ロジック・シグネチャ・可視性・コメントは変更しない。本体クラスには `use` 文と `require_once` を追加する。全マイルストーン完了後、本体クラスに残るのはプロパティ宣言・`__get`・`__call`・`__construct`・`loadExtension` のみ（約500行想定）。

新ファイルの規約（既存の命名に踏襲）:

- 置き場所: `manager/includes/traits/`
- ファイル名: `document.parser.<ドメイン>.trait.php`（例: `document.parser.tv.trait.php`）
- トレイト名: `DocumentParser<Domain>Trait`（例: `DocumentParserTvTrait`）
- 先頭は `<?php` とトレイト宣言のみ。名前空間・use宣言は既存に合わせて使わない。

### メソッドマッピング表（帰属の正本）

各トレイトに移すメソッドを列挙する。「本体」= document.parser.class.inc.php、「sub」= subparser トレイト由来。

1. `document.parser.request-response.trait.php` — `DocumentParserRequestResponseTrait`
   本体: executeParser, treatRequestUri, removeTrackingParameters, getDocumentIdentifier, _treatAliasPath, getRequestQ, sanitizeVars, sanitize_gpc, flattenToString, setUaType, getUaType, genQsHash, prepareResponse, _getTemplateCode, mergeScripts, outputContent, RecoveryEscapedTags, _getEscapedTags, parseNonCachedSnippets, postProcess, logPostProcessThrowable, normalizeTraceFrames, checkPreview, checkSiteStatus, get_static_pages, gotoSetup, reload, output, mergeRegisteredClientScripts, mergeRegisteredClientStartupScripts, getRegisteredClientScripts, getRegisteredClientStartupScripts
   sub: sendRedirect, sendForward, sendUnavailablePage, sendErrorPage, sendUnauthorizedPage, webAlertAndQuit, getPreviewObject, regClientCSS, regClientScript, regClientStartupHTMLBlock, regClientHTMLBlock, regClientStartupScript

2. `document.parser.tag-parse.trait.php` — `DocumentParserTagParseTrait`
   本体: getTagsFromContent, _getTagsFromContent, escaped_content, mergeDocumentContent, splitKeyAndFilter, getReadableValue, _contextValue, inheritDocId, mergeSettingsContent, mergeChunkContent, mergePlaceholderContent, mergeCommentedTagsContent, ignoreCommentedTagsContent, escapeLiteralTagsContent, mergeConditionalTagsContent, _prepareCTag, _parseCTagCMD, mergeBenchmarkContent, parseDocumentSource, _getSGVar, isSuperGlobalAccessor, fetchSuperGlobalValue, convertBracketToDot, getSuperGlobalSource, getChunk, hasChunk, _return_chunk_value, parseChunk, parseText, parseList, parsePlaceholder, cleanUpMODXTags, applyFilter, addFilter, ph, setPh, getPlaceholder, setPlaceholder, toPlaceholders, toPlaceholder
   sub: mergeInlineFilter

3. `document.parser.element-exec.trait.php` — `DocumentParserElementExecTrait`
   本体: evalPlugin, evalSnippet, resolveErrorReportingLevel, mapErrorReportingLevelToMask, enterErrorContext, leaveErrorContext, shouldProcessBufferedError, processBufferedError, handleElementThrowable, evalSnippets, runSnippet, _get_snip_result, getParamsFromString, _getSplitPosition, _split_snip_call, _getSnippetObject, setSnippetCache, getPluginCache, getPluginCode, getPluginProperties, setPluginCache, invokeEvent, removeAllEventListener, parseProperties, addSnippet, addChunk
   sub: getSnippetId, getSnippetName, addEventListener, removeEventListener

4. `document.parser.document-tree.trait.php` — `DocumentParserDocumentTreeTrait`
   本体: getDocumentObject, getParentIds, hasChildren, getSiblingIds, getSiblings, getChildIds, getDocuments, getDocument, getField, getPageInfo, getParent, doc, get_docfield_type
   sub: getDocumentChildrenTVars, getDocumentChildrenTVarOutput, getAllChildren, getActiveChildren, getDocumentChildren, updateDraft, setdocumentMap

5. `document.parser.url-alias.trait.php` — `DocumentParserUrlAliasTrait`
   本体: getAliasListing, setAliasListingByParent, getAliasFromID, getParentID, setParentIDByParent, getAliasPath, getUltimateParentId, makeUrl, rewriteUrls, _getReferenceListing, stripAlias, getIdFromAlias, getIdFromUrl
   sub: setAliasListing

6. `document.parser.auth-user.trait.php` — `DocumentParserAuthUserTrait`
   本体: token_auth, verifyBearerToken, saveBearerToken, isLoggedIn, getUserFromName, checkSession, hasPermission, getLoginUserID, getUserDocGroups, getDocGroups, changePassword
   sub: changeWebUserPassword, checkPermissions, isMemberOfWebGroup, getWebUserInfo, getUserInfo, getLoginUserName, getLoginUserType, genTokenString

7. `document.parser.cache-config.trait.php` — `DocumentParserCacheConfigTrait`
   本体: saveDBCache, getDBCache, purgeDBCache, getSiteCache, setSiteCache, checkCache, getCache, updatePublishStatus, getSettings, getWebUserSettings, getUserConfig, getConfig, config, setConfig, saveConfig, conf_var
   sub: clearCache, setCacheRefreshTime, setOption, getOption, regOption

8. `document.parser.tv.trait.php` — `DocumentParserTvTrait`
   本体: getTemplateVar, getTemplateVars, getTemplateVarOutput, tvProcessor
   sub: ProcessTVCommand, splitTVCommand, getExtention, decodeParamValue, parseInput, getUnixtimeFromDateString, renderFormElement, rendarFormText, rendarFormTextarea, rendarFormUrl, rendarFormDate, rendarFormSelect, rendarFormCheckbox, rendarFormRadio, rendarFormImage, rendarFormFile, rendarFormHidden, rendarFormCustom, custom_tv_tpl, ParseInputOptions, splitOption, isSelected, atBind, atBindFile, atBindUrl, atBindInclude

9. `document.parser.log-error.trait.php` — `DocumentParserLogErrorTrait`
   本体: phpError, addLogEntry
   sub: sendmail, rotate_log, addLog, logEvent, messageQuit, recDebugInfo, get_backtrace, normalizeLogMessage

10. `document.parser.utility.trait.php` — `DocumentParserUtilityTrait`（受け皿: 薄い汎用ヘルパー）
    本体: join, getMicroTime, getAbsolutePath, is_int, isInt, isBackend, isFrontend, toDateFormat, toTimeStamp, mb_strftime, getFullTableName, getManagerPath, getCachePath, stripTags, nicesize, saveToFile, setBaseTime, getBaseTime, htmlspecialchars, hsc, move_uploaded_file, sanitizeUploadedFilename, resizeImage, input_get, input_post, input_cookie, input_any, server, server_var, session, session_var, global_var, array_get, array_set, html_tag, real_ip
    sub: getMimeType, loadLexicon

11. `document.parser.misc.trait.php` — `DocumentParserMiscTrait`（受け皿: レガシー・将来のdeprecation候補）
    本体: dbConnect, dbQuery, recordCount, fetchRow, affectedRows, insertId, dbClose, putChunk
    sub: snapshot, getVersionData

## Concrete Steps

作業ブランチはマイルストーンごとに main（または統合ブランチ）から切り、1マイルストーン=1コミットとする。

### M0: 棚卸しベースライン作成（コード変更なし）

実行コマンド（リポジトリルートで実行。以降のマイルストーンでも同じコマンドを使う）:

    grep -hoE 'function +&?[a-zA-Z0-9_]+' \
      manager/includes/document.parser.class.inc.php \
      manager/includes/traits/*.trait.php \
      | sed 's/function *&*//' | sort | uniq -c > /tmp/parser-methods-baseline.txt
    wc -l /tmp/parser-methods-baseline.txt

    grep -hoE '(public|protected|private)?\s*(static\s+)?function\s+&?[a-zA-Z0-9_]+\s*\([^)]*\)' \
      manager/includes/document.parser.class.inc.php \
      manager/includes/traits/*.trait.php \
      | tr -s ' ' | sort > /tmp/parser-signatures-baseline.txt
    wc -l /tmp/parser-signatures-baseline.txt

期待される観測結果: 1本目は約270行のメソッド名一覧（各行の先頭カウントはすべて 1。2 があればその時点で重複定義であり調査が必要）。2本目は可視性・static修飾・引数リストまで含めたシグネチャ一覧で、メソッド名だけでは検出できない可視性変更やシグネチャ変更を比較するための基準にする。両ファイルを以降の全マイルストーンで比較基準にする。

### M1〜M10 共通手順（マイルストーンごとに繰り返す）

1. 編集対象: `manager/includes/traits/document.parser.<ドメイン>.trait.php` を新規作成し、マッピング表のメソッドを本体クラス・subparser トレイトから doc コメントごと切り取って移す。移動のみで書き換えない。
2. 編集対象: `manager/includes/document.parser.class.inc.php` — ファイル先頭の require 群に `require_once(__DIR__ . '/traits/document.parser.<ドメイン>.trait.php');` を追加し、クラス冒頭の `use DocumentParserSubParserTrait;` の並びに `use DocumentParser<Domain>Trait;` を追加する。
3. 構文チェック:

       php -l manager/includes/document.parser.class.inc.php
       php -l manager/includes/traits/document.parser.<ドメイン>.trait.php
       php -l manager/includes/traits/document.parser.subparser.trait.php

   期待: すべて「No syntax errors detected」。

4. 合成チェック（トレイトのメソッド名衝突を検出）:

       php -r "define('MODX_API_MODE', true); require 'manager/includes/document.parser.class.inc.php'; echo class_exists('DocumentParser') ? 'compose OK' : 'NG';"

   期待: `compose OK`。Fatal error が出た場合はメソッドの二重定義（移動漏れ・消し漏れ）。

5. 棚卸し比較: M0と同じ2本の grep コマンドをそれぞれ `/tmp/parser-methods-after.txt` / `/tmp/parser-signatures-after.txt` に出力し、

       diff /tmp/parser-methods-baseline.txt /tmp/parser-methods-after.txt
       diff /tmp/parser-signatures-baseline.txt /tmp/parser-signatures-after.txt

   期待: いずれも差分なし（メソッドの総数・名前・重複数に加え、可視性・static修飾・引数リストまで分割前と完全一致）。差分が出た場合は可視性やシグネチャを変更してしまっている可能性があるため移動作業をやり直す。

6. トレイト帰属確認: 当該マイルストーンでマッピング表に列挙したメソッド名について、新トレイトファイルに全て存在し、旧ファイル（本体クラス・subparserトレイト）から消えていることを確認する。

       # 例: M1(tv)の場合、マッピング表の該当メソッド名を1行ずつ書いた一時ファイルを用意して
       while read -r m; do grep -q "function &*$m(" manager/includes/traits/document.parser.tv.trait.php || echo "MISSING: $m"; done < /tmp/milestone-methods.txt
       while read -r m; do grep -l "function &*$m(" manager/includes/document.parser.class.inc.php manager/includes/traits/document.parser.subparser.trait.php 2>/dev/null && echo "STILL IN OLD LOCATION: $m"; done < /tmp/milestone-methods.txt

   期待: どちらの出力も空（全メソッドが意図したトレイトに帰属し、旧ファイルに残っていない）。

7. 実機スモークテスト（DBを伴うフルブート）:

       docker compose exec app php evo help

   期待: コマンド一覧が表示される（bootstrap が `new DocumentParser` に成功した証拠）。加えてブラウザでフロントページ表示と管理画面ログインを確認する。マイルストーン固有の確認点は下記。

8. コミットして次のマイルストーンへ。

### マイルストーン固有のスモーク確認点

- M1 (tv): 管理画面でドキュメント編集画面を開き、TV入力フォーム（テキスト・日付・セレクト等）が描画されること。
- M2 (log-error): 管理画面「システムイベントログ」が表示され、既存ログが読めること。
- M3 (utility/misc): フロントページの日付表示等が正常なこと。
- M4 (url-alias): フレンドリーURLでのページ遷移とリンク書き換え（`rewriteUrls`）が機能すること。
- M5 (auth-user): 管理画面のログアウト→ログインが通ること。
- M6 (cache-config): 管理画面「サイトキャッシュを削除」実行後もフロントが表示されること。
- M7 (document-tree): 管理画面のドキュメントツリーが表示され、子ドキュメント一覧系スニペット（Ditto等が有れば）が動くこと。
- M8 (element-exec): スニペット・プラグインを含むページが描画されること。
- M9 (tag-parse): チャンク・プレースホルダ・条件タグを含むページが描画されること。
- M10 (request-response): フロント表示・404/未認可ページ・リダイレクトが機能すること。

### M10 の追加手順（subparser 廃止）

`document.parser.subparser.trait.php` の末尾（2848–2856行）には `@deprecated` 注記付きの `SubParser` クラスが定義されており、`DocumentParserSubParserTrait` を `use` している。ファイルを単純削除するとこのクラスも消え、レガシー連携が `new SubParser()` した場合にクラス未定義エラーになる（Decision Log 参照）。そのため削除とBC維持を分けて行う。

1. 編集対象: `manager/includes/traits/document.parser.subparser.legacy.php` を新規作成し、次の内容のみを記載する（`@deprecated` の `SubParser` クラス定義を新設11トレイトの `use` で再構成）:

       <?php

       if (!class_exists('SubParser')) {
           /**
            * @deprecated Use DocumentParser methods directly.
            */
           class SubParser
           {
               use DocumentParserRequestResponseTrait;
               use DocumentParserTagParseTrait;
               use DocumentParserElementExecTrait;
               use DocumentParserDocumentTreeTrait;
               use DocumentParserUrlAliasTrait;
               use DocumentParserAuthUserTrait;
               use DocumentParserCacheConfigTrait;
               use DocumentParserTvTrait;
               use DocumentParserLogErrorTrait;
               use DocumentParserUtilityTrait;
               use DocumentParserMiscTrait;
           }
       }

2. 残メソッドをすべて移動後、`manager/includes/traits/document.parser.subparser.trait.php` にメソッドが残っていないことを確認してファイルを削除する。
3. 編集対象: `manager/includes/document.parser.class.inc.php` — `require_once(__DIR__ . '/traits/document.parser.subparser.trait.php');` の行を `require_once(__DIR__ . '/traits/document.parser.subparser.legacy.php');` に置き換え、`use DocumentParserSubParserTrait;` を削除する（新設11トレイトの `use` はM1〜M9で追加済みのためここでは変更不要）。
4. `loadExtension()` 内の `case 'subparser': return true;` は互換シムとして**残す**（レガシープラグインが `$modx->loadExtension('SubParser')` を呼ぶ可能性があるため）。
5. 削除後の最終検証（このコミット単体で壊れていないことを確認する）:

       php -l manager/includes/document.parser.class.inc.php
       php -l manager/includes/traits/document.parser.subparser.legacy.php
       php -r "define('MODX_API_MODE', true); require 'manager/includes/document.parser.class.inc.php'; echo (class_exists('DocumentParser') \&\& class_exists('SubParser')) ? 'compose OK' : 'NG';"
       docker compose exec app php evo help

   期待: `php -l` はすべて「No syntax errors detected」、合成チェックは `compose OK`、`evo help` はコマンド一覧を表示する。

6. 参照残りの最終確認:

       grep -rln "DocumentParserSubParserTrait" --include="*.php" .
       find manager/includes/traits -name "document.parser.subparser.trait.php"

   期待: いずれも該当なし（`document.parser.subparser.legacy.php` は意図した残存物なのでヒットして良い）。

## Validation and Acceptance

全マイルストーン完了時点で、次のすべてが観察できれば完了とする。

1. `manager/includes/traits/` に新トレイト11ファイル＋`document.parser.subparser.legacy.php`（deprecated `SubParser` クラス定義）が存在し、`document.parser.subparser.trait.php` が存在しない。
2. `manager/includes/document.parser.class.inc.php` が概ね500行程度（プロパティ・マジックメソッド・コンストラクタ・loadExtension のみ）。
3. M0ベースラインとの棚卸し diff（メソッド名一覧・シグネチャ一覧の両方）が空（公開APIのメソッド集合・可視性・シグネチャが分割前と完全一致）。
4. `docker compose exec app php evo help` がコマンド一覧を表示する。
5. ブラウザで、フロントページ表示・管理画面ログイン・ドキュメント編集画面のTVフォーム描画・システムイベントログ表示がすべて分割前と同様に動作する。
6. `php -r "define('MODX_API_MODE', true); require 'manager/includes/document.parser.class.inc.php'; var_dump(class_exists('SubParser'));"` が `bool(true)` を返す（レガシー `SubParser` クラスの後方互換が保たれている）。

## Idempotence and Recovery

- 各マイルストーンは独立コミットなので、問題発生時は `git revert <コミット>` または `git reset --hard HEAD~1`（未push時）で丸ごと戻せる。トレイト分割は状態を持たないため、戻した後に再実行しても副作用はない。
- 途中中断時の現在地確認: `ls manager/includes/traits/` で作成済みトレイトを数え、Progress のチェック状態と突き合わせる。合成チェックのワンライナー（Concrete Steps 手順4）が `compose OK` を返せばその時点のツリーは健全。
- 棚卸しベースライン `/tmp/parser-methods-baseline.txt` が消えた場合は、分割開始前のコミットを `git stash` / `git worktree` 等で参照して M0 のコマンドを再実行すれば再生成できる。

## Artifacts and Notes

- 対象: `manager/includes/document.parser.class.inc.php`, `manager/includes/traits/document.parser.subparser.trait.php`（廃止）, `manager/includes/traits/document.parser.subparser.legacy.php`（M10で新規作成）
- 検証補助: `manager/includes/cli/bootstrap.php`（69–70行で `new DocumentParser`）, `compose.yml`（appサービス）
- 関連ドキュメント: `AGENTS.md`（コミット規約・docker実行）, `assets/docs/architecture.md`
- 想定コミット単位（Conventional Commits・日本語）:
  - M1: `refactor(parser): TV関連メソッドをDocumentParserTvTraitへ分離`
  - M2: `refactor(parser): ログ・エラー処理をDocumentParserLogErrorTraitへ分離`
  - M3: `refactor(parser): 汎用ヘルパーをutility/miscトレイトへ分離`
  - M4: `refactor(parser): URL・エイリアス処理をDocumentParserUrlAliasTraitへ分離`
  - M5: `refactor(parser): 認証・ユーザー処理をDocumentParserAuthUserTraitへ分離`
  - M6: `refactor(parser): キャッシュ・設定処理をDocumentParserCacheConfigTraitへ分離`
  - M7: `refactor(parser): ドキュメントツリー取得をDocumentParserDocumentTreeTraitへ分離`
  - M8: `refactor(parser): エレメント実行処理をDocumentParserElementExecTraitへ分離`
  - M9: `refactor(parser): タグ解析処理をDocumentParserTagParseTraitへ分離`
  - M10: `refactor(parser): リクエスト処理を分離しsubparserトレイトを廃止`

## Interfaces and Dependencies

- 外部インターフェースは `$modx`（DocumentParserインスタンス）の public メソッド群であり、本計画では**一切変更しない**。スニペット・プラグイン・モジュール・manager/actions からの呼び出しはすべて従来どおり動く。
- オートローダーは存在しないため、トレイトの読み込みは `document.parser.class.inc.php` 先頭の `require_once` 群で行う（既存方式の踏襲）。
- `loadExtension('subparser')` はレガシー互換シムとして維持する。extenders（DBAPI・ManagerAPI 等）の仕組みには手を入れない。
- レガシー `SubParser` クラス（`@deprecated`）は `document.parser.subparser.legacy.php` として存続させ、`new SubParser()` する外部連携との互換を保つ。
- 依存タスクなし。ただし本計画進行中は同2ファイルへの他の変更とコンフリクトしやすいため、並行作業がある場合はマイルストーン境界でリベースする。
