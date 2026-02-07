# ExecPlan: postProcess() mkdir TOCTOU競合の修正

## Purpose / Big Picture
`postProcess()` のキャッシュディレクトリ作成で、同時リクエストによる TOCTOU（Time of Check to Time of Use）競合の WARNING を解消する。ユーザーにエラー画面が表示されるのを防ぐ。

## Progress
- [x] (2026-02-07) L884-886 の mkdir を競合安全なパターンに置換
- [x] (2026-02-07) L890-892 の mkdir を競合安全なパターンに置換
- [ ] (2026-02-07) 動作確認（キャッシュクリア後のページ表示）

## Surprises & Discoveries
（実装中に記録）

## Decision Log
- (2026-02-07) `@mkdir() + is_dir()` パターンを採用。理由: PHP公式ドキュメントでも推奨される定番パターン。`is_dir()` → `mkdir()` の事前チェック方式は競合を根本的に解決できない。`ex_export_site.php` の mkdir はシングルプロセスのCLI操作のため対象外とする。

## Outcomes & Retrospective
（完了後に記録）

## Context and Orientation
対象ファイル: `manager/includes/document.parser.class.inc.php` の `postProcess()` メソッド内（L884-892）。

ページキャッシュ書き込み前にキャッシュディレクトリを作成する処理で、現在は `is_dir()` で存在チェックしてから `mkdir()` を呼ぶ方式。同時リクエスト時にチェックと作成の間に別リクエストがディレクトリを作成すると `mkdir(): File exists` WARNING が発生する。

報告元: https://forum.modx.jp/viewtopic.php?f=32&t=2031

## Plan of Work
`is_dir()` による事前チェックを廃止し、`@mkdir()` で WARNING を抑制しつつ事後に `is_dir()` で存在確認するパターンに置換する。mkdir が失敗し、かつディレクトリも存在しない場合（パーミッション不足等）は RuntimeException を投げて問題を明確にする。

## Concrete Steps

### Step 1: L884-886 の修正（UAタイプ別ディレクトリ）

修正前:

    if (!is_dir(MODX_CACHE_PATH . $this->uaType)) {
        mkdir(MODX_CACHE_PATH . $this->uaType, 0777);
    }

修正後:

    if (!@mkdir(MODX_CACHE_PATH . $this->uaType, 0777) && !is_dir(MODX_CACHE_PATH . $this->uaType)) {
        throw new \RuntimeException('Failed to create cache directory: ' . MODX_CACHE_PATH . $this->uaType);
    }

### Step 2: L890-892 の修正（URI親ディレクトリ）

修正前:

    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

修正後:

    if (!@mkdir($path, 0777, true) && !is_dir($path)) {
        throw new \RuntimeException('Failed to create cache directory: ' . $path);
    }

### Step 3: 動作確認
1. `temp/` 配下のページキャッシュを削除
2. ブラウザでフロントページを表示し、キャッシュが正常に生成されることを確認
3. WARNING が出ないことを確認

## Validation and Acceptance
- `temp/` 配下にキャッシュディレクトリとキャッシュファイルが正常に生成される
- PHP WARNING が発生しない
- 既存のキャッシュディレクトリがある状態でもエラーにならない（@mkdir が false を返すが is_dir が true なので正常通過）

## Idempotence and Recovery
修正は2箇所の `mkdir` パターン置換のみ。`git checkout -- manager/includes/document.parser.class.inc.php` で即座に元に戻せる。

## Artifacts and Notes
- フォーラム報告: https://forum.modx.jp/viewtopic.php?f=32&t=2031
- 対象ブランチ: `fix/forum-2031-mkdir-race-condition`
- 関連ドキュメント: `assets/docs/cache-mechanism.md`

## Interfaces and Dependencies
外部依存なし。`postProcess()` の内部処理のみの変更で、メソッドのインターフェースに変更はない。
