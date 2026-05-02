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
