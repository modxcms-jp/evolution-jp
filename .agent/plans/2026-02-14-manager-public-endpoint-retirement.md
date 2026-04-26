# ExecPlan: `manager` 公開エンドポイント廃止と段階的ディレクトリ整理（Phase 0.8）

## Purpose / Big Picture

`manager/` を公開URLとして廃止し、Router経由の単一入口運用へ移行する。物理ディレクトリ削除は即時に行わず、互換期間を経て段階的に整理することで運用リスクを下げる。

## Progress

- [ ] (2026-02-14) `manager` 公開URL廃止ポリシー（互換期間・リダイレクト方針）を確定する
- [ ] (2026-02-14) Web到達経路をRouter経由へ集約する
- [ ] (2026-02-14) 旧 `manager/` URLの互換導線を実装する
- [ ] (2026-02-14) 物理ディレクトリ整理の移行計画（段階削除）を策定する
- [ ] (2026-02-14) 監視・ロールバック手順を整備する

## Surprises & Discoveries

- URL統合と物理構成変更は同時実施すると失敗時の切り戻し範囲が広がるため、段階分離が必須。

## Decision Log

- 2026-02-14 / AI / まず「公開URLとしての `manager` 廃止」を先行し、物理ディレクトリ整理は後段で実施する。
- 2026-02-14 / AI / 互換期間中は旧URLへのアクセスを制御付きで許容し、監視ログをもとに最終停止時期を判断する。
- 2026-02-14 / AI / 単一フロントコントローラ方針を優先し、直接ディレクトリアクセスを前提にしない設計へ移行する。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- 公開URL廃止: URLとしてアクセス可能な入口を停止し、内部実装は維持する段階。
- 互換期間: 旧URLを限定的に残し、移行を段階化する期間。

対象ファイル:

- `index.php`
- `manager/index.php`
- Webサーバー設定（Apache/Nginx ルールは運用手順として記録）
- `.agent/roadmap.md`

前提依存:

- `.agent/plans/2026-02-14-api-router-foundation.md`
- `.agent/plans/2026-02-14-manager-url-routing-migration.md`

## Plan of Work

まずRouter側で管理導線を確定し、旧 `manager/` の直接到達を縮退させる。次に互換リダイレクトと監視ログを導入し、アクセス実績を観測する。利用が収束した時点で物理ディレクトリの段階整理に進む。常に復旧可能性を優先し、URL停止とファイル削除を同時に行わない。

## Concrete Steps

1. 廃止ポリシーを定義する。  
   編集対象ファイル: `.agent/plans/2026-02-14-manager-public-endpoint-retirement.md`  
   実行コマンド: `rg -n "互換期間|ロールバック|Validation and Acceptance" .agent/plans/2026-02-14-manager-public-endpoint-retirement.md`  
   期待される観測結果: 停止条件と復旧条件が明文化される。
2. 旧URLの到達制御を実装する。  
   編集対象ファイル: `index.php`, `manager/index.php`  
   実行コマンド: `php -l index.php manager/index.php`  
   期待される観測結果: 旧 `manager/` が定義した移行挙動（redirect/deny）になる。
3. 監視ログを実装する。  
   編集対象ファイル: `manager/index.php`（必要時）  
   実行コマンド: `rg -n "logEvent|manager|redirect|deprecated" manager/index.php`  
   期待される観測結果: 旧URLアクセスが観測可能になる。
4. 物理整理のチェックリストを作る。  
   編集対象ファイル: `.agent/roadmap.md`  
   実行コマンド: `rg -n "manager|単一フロントコントローラ|Phase 0.8" .agent/roadmap.md`  
   期待される観測結果: 段階削除の条件が追記される。

## Validation and Acceptance

1. `php -l index.php manager/index.php` が成功すること。
2. 新管理URLでログイン・主要遷移が成功すること。
3. 旧 `manager/` URLアクセスが定義どおりに処理されること。
4. 旧URLアクセスが監視ログに記録されること。
5. ロールバック手順で旧導線へ復帰できること。

## Idempotence and Recovery

「URL制御」「監視」「物理整理計画」を分離して反映する。障害時はURL制御のみを戻せるようにし、物理削除は最終段階まで実行しない。

## Artifacts and Notes

- `.agent/roadmap.md`
- `.agent/plans/2026-02-14-api-router-foundation.md`
- `.agent/plans/2026-02-14-manager-url-routing-migration.md`

## Interfaces and Dependencies

依存:

- Router優先解決ルール
- `manager_prefix` 設定化

後続依存:

- `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- `.agent/plans/2026-02-14-headless-read-api.md`
- `.agent/plans/2026-02-14-manager-write-api.md`
