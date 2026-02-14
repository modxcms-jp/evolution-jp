---
name: issue-resolver
description: 不具合報告（フォーラム/Issue）に基づく調査・再現・修正・記録のフルサイクルを支援するスキル。URLからの情報取得や再現コード作成を含みます。
---

# Issue Resolver

不具合報告を起点に、調査から修正・記録までを行うワークフロー。
実装時のコーディング規約は `project-worker` スキルに従う。

## 実行ルール

1. このスキルはローカル開発環境専用として扱う。
2. 調査と修正は効率優先・トークン節約優先で進める。
3. 修正方針を先に判定し、軽微な修正は ExecPlan 作成を省略して実装へ進んでよい（ただし調査報告への承認後に限る）。
4. 問題が分かりにくい場合や修正が複雑な場合は `exec-plan` を作成してから進める。
5. 検証に必要な範囲でローカルDBの更新・削除・初期化を許容する。
6. 本番接続情報や本番データを扱う前提は置かない。
7. フォーラムURLの本文取得は最初から制限外実行で行い、`curl` / `wget` を順に試す。
8. URL取得コマンド実行時は都度 `require_escalated` を使い、承認付きで実行する。
9. `Could not resolve host` などDNS解決失敗時は、サンドボックス内制限として扱い、制限外実行へ切り替える。
10. URL取得に失敗した状態でローカルコードだけから原因を推測しない。取得不能時は必ずエンジニアへ対応（本文提供・制限外実行）を依頼する。
11. 原因が判明した時点で必ず先に調査報告を提出し、エンジニア承認前に修正へ進まない。
12. `evo` コマンドとDB参照はホスト側で直接実行しない。必ず Docker コンテナ内で実行する。
13. エラー隠蔽を目的とした修正を禁止する。警告/例外を消すための握りつぶしや無条件の値変換を行わず、原因となる不正データの発生源を修正する。
14. 例: `strpos()` に `null` が渡って落ちる場合、`null` を空文字へ変換して通すのではなく、`null` が渡る経路を特定して上流で是正する。

## コマンド

### analyze-issue <URL|テキスト>
1. URLなら最初から制限外実行で本文取得し、HTMLタグを除去したテキストのみを扱う: `curl -fsSL -A "Mozilla/5.0" "<URL>" | php -r '$h=stream_get_contents(STDIN); $t=strip_tags($h); $t=preg_replace("/\s+/u"," ",$t); echo trim($t), PHP_EOL;'` を承認付きで実行 → 失敗時 `wget -qO- "<URL>" | php -r '$h=stream_get_contents(STDIN); $t=strip_tags($h); $t=preg_replace("/\s+/u"," ",$t); echo trim($t), PHP_EOL;'` を同様に承認付きで実行
2. `AGENTS.md` のドキュメントマップから関連ファイルを特定
3. `docker compose exec <app-service> php evo config:show` で関連設定値、`docker compose exec <app-service> php evo db:describe` で関連テーブル構造を確認
4. 現象の要約と原因仮説を3つ提示
5. 原因が判明した場合は、修正前に「原因・影響範囲・修正方針案」を短く報告
6. URL本文を取得できない場合は、失敗理由を1行で記録し、環境制限またはアクセス制限の種別を明記する
7. 制限外実行でも取得できない場合は、本文貼り付けを依頼
8. URL本文が得られるまでは原因推測や修正方針の断定を行わず、エンジニアの対応を待つ
9. 情報不足時はユーザーへの質問リストを作成

### reproduce
- 現象を再現する最小限のPHPコードを作成
- `docker compose exec <app-service> php evo db:query` でデータ状態を確認し再現条件を特定
- デバッグ用ログ (`evo()->logEvent(...)`) の挿入箇所を提案
- `docker compose exec <app-service> php evo cache:clear` でキャッシュクリアしてから再現確認

### create-branch
- Issue番号・内容からブランチ名を提案（例: `fix/10705-tv-saving-error`）
- mainから分岐して作成

### draft-plan (必要時)
- 問題が分かりにくい場合や修正が複雑な場合のみ `exec-plan` スキルの `/create-plan` に委譲する
- analyze-issue の調査結果をタスク概要として渡す

### implement-fix
- 調査報告に対するエンジニア承認を確認してから修正を開始
- draft-planに基づきコードを修正
- `project-worker` スキルの規約を厳守
- 修正後 `docker compose exec <app-service> php evo cache:clear` でキャッシュクリア

### archive
- Conventional Commits形式のコミットメッセージ生成（例: `fix(manager): resolve tv saving error on php8.2 (Ref forum#10705)`）
- `assets/docs/troubleshooting/solved-issues.md` にナレッジを追記

### pull-request
- `gh` CLIでPR作成（push → pr create）
- PR本文（What / Why / How）をドラフト生成
