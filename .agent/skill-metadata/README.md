# Skill Metadata

このディレクトリは、スキルごとの寿命管理と集計の正本を置く。
`skill:sync` は run と archive の結果から `inventory.json` / `stats.json` / `history.jsonl` を更新する。

## 標準構成

```text
.agent/skill-metadata/<skill>/
├── inventory.json
├── stats.json
└── history.jsonl
```

## 使い分け

- `inventory.json`: いま有効な項目の一覧
- `stats.json`: 使用回数、効果、陳腐化の集計
- `history.jsonl`: 変更履歴の追跡

## テンプレート

`skill:init` は各 skill ディレクトリに初回ファイルを作成する。
`templates/` 配下の example は、初期内容の参照用として使う。

## 運用ルール

- `templates/` は参照専用であり、skill 名としては使用しない。
- `inventory.json` / `stats.json` は `skill:sync` で再集計して更新する（手編集を正本にしない）。
- `history.jsonl` は `skill:sync` の追記履歴を保持する。`skill:validate --strict` では存在が必須。
- stale 判定は `skill:prune` の出力を基準にし、`retire` / `move` / `merge` 候補を proposal へ反映する。
