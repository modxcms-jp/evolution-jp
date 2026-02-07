# ユーザー管理の整合性改善

## 問題

`manager_users` と `user_attributes` の挿入がアトミックでないため、片方のみ存在する孤立レコードが発生しうる。PHP のエラーやネットワーク断により、ログインできないユーザーや参照先のないレコードが残る。原因はトランザクション保護・外部キー制約の両方が欠如していること。

## 方針

1. **トランザクション実装**（高優先）: 各プロセッサで `START TRANSACTION` → 成功時 `COMMIT` / 失敗時 `ROLLBACK` + `evo()->logEvent()` でエラー記録
2. **外部キー制約**（中優先）: `user_attributes.internalKey` → `manager_users(id)` に `ON DELETE CASCADE / ON UPDATE CASCADE`
3. **バリデーション強化**（低優先）: ユーザー名重複・メール形式（`filter_var`）・ロール存在チェック

## 対象ファイル

- `manager/processors/save_user.processor.php` — ユーザー作成・更新
- `manager/processors/delete_user.processor.php` — ユーザー削除
- `manager/processors/change_password.processor.php` — パスワード変更
- `install/sql/create_tables.sql` — 新規インストール時に外部キー制約を含める

## 前提条件

- InnoDB 必須（MyISAM の場合は `ALTER TABLE ... ENGINE=InnoDB` で変換）
- 外部キー追加前に孤立レコードの解消が必要（`LEFT JOIN ... WHERE ... IS NULL` で検出し、不足する `user_attributes` は INSERT、孤立した `user_attributes` は DELETE）
