# ExecPlan: CLI優先・ブラウザ任意の表示確認スキル作成

## Purpose / Big Picture

AI自走での動作確認を、デフォルトはCLIで軽量実行し、必要時のみブラウザ目視確認へ切り替えられるスキルとして標準化する。これにより、検証速度と再現性を保ちながら、UIの最終確認も同一ワークフローで扱えるようにする。

## Progress

- [x] (2026-02-21) 要件（CLI優先・ブラウザ任意・Playwright任意）を仕様化する
- [x] (2026-02-21) スキル雛形を作成する
- [x] (2026-02-21) CLI検証フローを実装する
- [x] (2026-02-21) Playwright検証フロー（headless既定 / headed任意）を実装する
- [x] (2026-02-21) バリデーションと使用手順を確定する

## Surprises & Discoveries

- 現在のローカルスキルには、ブラウザ表示確認専用スキルが存在しない。
- 既存運用では `php evo` や `db:query` を併用するCLI確認が中心で、ブラウザ確認は手動運用になりやすい。
- ブラウザ起動は環境負荷と実行時間が増えるため、既定をCLIに固定する設計が適している。
- 管理画面のアクセス制御は `HTTP_ACCEPT_LANGUAGE` 前提で、ヘッダ未指定だと `/manager/` が 404 になる。
- Playwright は実行環境によってサンドボックス制約で起動失敗することがある（`Operation not permitted`）。
- 管理画面の投稿テストでは、RTE有効時に `textarea` への通常入力だけでは本文が保存されない場合がある。

## Decision Log

- 2026-02-21 / AI / スキルの既定モードを「CLI-only」にする。根拠: 軽量・高速・再現性が高い。
- 2026-02-21 / AI / Playwrightはオプション機能として提供し、`headless` を既定にする。代替案: 常時headed。見送り理由: 負荷が高くCI/自走運用に不向き。
- 2026-02-21 / AI / 目視確認は `headed` を明示指定した場合のみ実施する。根拠: ユーザー意図に応じて負荷と確実性を切り替えられる。
- 2026-02-21 / AI / 管理画面検証手順へ `Accept-Language` ヘッダ必須を追記する。根拠: ヘッダ無しアクセスが404になり誤検知を誘発する。
- 2026-02-21 / AI / Playwright起動失敗時は制限外実行へ切り替える手順を標準化する。根拠: 環境依存のサンドボックス制約を運用で吸収するため。
- 2026-02-21 / AI / 投稿テストは「RTE API同期→保存後DB確認」を標準手順にする。根拠: 画面遷移のみでは本文未保存を見逃すため。

## Outcomes & Retrospective

- `.codex/skills/adaptive-validation-runner/` を新規作成し、`SKILL.md` / `agents/openai.yaml` / `references/checklist.md` を整備した。
- スキル表示名を `Adaptive Validation Runner` に更新し、CLI優先かつ必要時E2Eへ切替える意図を名称に反映した。
- スキル既定モードを `cli` に固定し、ブラウザ起動は `playwright-headed` 明示時のみとした。
- `quick_validate.py` で `Skill is valid!` を確認した。
- 管理画面向けのCLI例に `Accept-Language` ヘッダを追加し、404誤判定を回避する運用へ更新した。
- Playwrightのサンドボックス失敗時フォールバック（制限外実行）をスキル手順へ反映した。
- 投稿テスト向けに、RTE同期手順と `content` カラム確認（保存後DB検証）をチェックリストへ反映した。

## Context and Orientation

用語:

- CLI検証: `curl` / `php evo` / ログ確認でレスポンス・状態を検証する方式。
- Headless: ブラウザUIを表示せず自動実行する方式。
- Headed: ブラウザUIを表示して目視確認する方式。

対象ファイル:

- `.codex/skills/adaptive-validation-runner/SKILL.md`（新規）
- `.codex/skills/adaptive-validation-runner/agents/openai.yaml`（新規）
- `.codex/skills/adaptive-validation-runner/references/checklist.md`（新規）
- （必要時）`.codex/skills/adaptive-validation-runner/scripts/*.sh|*.js`（新規）

関連既存ファイル:

- `.codex/skills/exec-plan/SKILL.md`
- `/home/yamamoto/.codex/skills/.system/skill-creator/SKILL.md`
- `manager/includes/cli/README.md`

## Plan of Work

まずスキル本体に「モード選択規約（cli / playwright-headless / playwright-headed）」を定義し、既定をCLIに固定する。次にCLI検証テンプレート（URL確認、HTTPステータス、主要文言、ログ確認）を先に実装する。Playwrightは任意モードとして切り出し、headlessを標準・headedを明示オプションに限定する。最後に、出力フォーマットを統一し、取得根拠（コマンド、結果、スクリーンショット有無）を必須化して再現性を担保する。

## Concrete Steps

1. スキル仕様を固定する。  
   編集対象ファイル: なし（調査）  
   実行コマンド: `sed -n '1,220p' /home/yamamoto/.codex/skills/.system/skill-creator/SKILL.md`  
   期待される観測結果: スキル構成要件（frontmatter、resources、validation）が確認できる。

2. スキル雛形を初期化する。  
   編集対象ファイル: `.codex/skills/adaptive-validation-runner/*`（新規）  
   実行コマンド: `python3 .../init_skill.py adaptive-validation-runner --path .codex/skills --resources references,scripts`  
   期待される観測結果: `SKILL.md` と `agents/openai.yaml`、必要resourceが生成される。

3. CLI優先フローを実装する。  
   編集対象ファイル: `.codex/skills/adaptive-validation-runner/SKILL.md`, `.codex/skills/adaptive-validation-runner/references/checklist.md`  
   実行コマンド: `rg -n "cli|curl|php evo|default mode" .codex/skills/adaptive-validation-runner -S`  
   期待される観測結果: 既定がCLI-onlyであること、確認手順と出力テンプレートが明記される。

4. Playwright任意フローを実装する。  
   編集対象ファイル: `.codex/skills/adaptive-validation-runner/SKILL.md`（必要ならscriptsも追加）  
   実行コマンド: `rg -n "playwright|headless|headed|optional" .codex/skills/adaptive-validation-runner -S`  
   期待される観測結果: `headless` 既定、`headed` 明示指定時のみの運用が定義される。

5. スキル妥当性を検証する。  
   編集対象ファイル: なし（検証）  
   実行コマンド: `python3 .../quick_validate.py .codex/skills/adaptive-validation-runner`  
   期待される観測結果: `Skill is valid!` が出力される。

## Validation and Acceptance

1. スキル説明に「CLI優先」「ブラウザ確認は任意」が明記されていること。
2. 実行モードが `cli` / `playwright-headless` / `playwright-headed` の3種類で定義されていること。
3. `playwright-headed` が明示指定されない限りブラウザUIを起動しないこと。
4. `quick_validate.py` が成功すること。
5. 出力テンプレートに根拠（コマンド/ログ/スクリーンショット有無）が含まれること。

## Idempotence and Recovery

スキル作成は `.codex/skills/adaptive-validation-runner/` 配下に閉じる。途中で方針変更が必要な場合は同ディレクトリだけを編集すれば復帰できる。Playwright関連を見送る場合でも、CLI-onlyモードだけで有効なスキルとして成立させる。

## Artifacts and Notes

- `.agent/PLANS.md`
- `/home/yamamoto/.codex/skills/.system/skill-creator/SKILL.md`
- `manager/includes/cli/README.md`
- 既存スキル参照:
  - `.codex/skills/exec-plan/SKILL.md`
  - `.codex/skills/url-article-reader/SKILL.md`

## Interfaces and Dependencies

- スキル基盤: Codex skills (`SKILL.md`, `agents/openai.yaml`)
- 検証手段:
  - CLI: `curl`, `php evo`, 必要に応じて `docker compose exec`
  - Browser(optional): Playwright（headless/headed）
- 外部依存は任意導入とし、未導入環境ではCLI-onlyで完結できる設計にする。
