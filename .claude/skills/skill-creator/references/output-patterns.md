# 出力パターン

スキルが一貫性のある高品質な出力を生成する必要がある場合に、これらのパターンを使用してください。

## テンプレートパターン

出力形式のテンプレートを提供します。厳格さのレベルをニーズに合わせて調整してください。

**厳格な要件の場合（APIレスポンスやデータフォーマットなど）：**

```markdown
## レポート構造

必ずこの正確なテンプレート構造を使用してください：

# [分析タイトル]

## エグゼクティブサマリー
[主要な発見の1段落の概要]

## 主要な発見
- 裏付けデータを含む発見1
- 裏付けデータを含む発見2
- 裏付けデータを含む発見3

## 推奨事項
1. 具体的な実行可能な推奨事項
2. 具体的な実行可能な推奨事項
```

**柔軟なガイダンスの場合（適応が有用な場合）：**

```markdown
## レポート構造

これは合理的なデフォルト形式ですが、最善の判断を使用してください：

# [分析タイトル]

## エグゼクティブサマリー
[概要]

## 主要な発見
[発見した内容に基づいてセクションを適応]

## 推奨事項
[特定のコンテキストに合わせて調整]

特定の分析タイプに必要に応じてセクションを調整してください。
```

## 例パターン

出力品質が例を見ることに依存するスキルの場合、入力/出力のペアを提供します：

```markdown
## コミットメッセージ形式

これらの例に従ってコミットメッセージを生成します：

**例1：**
入力: Added user authentication with JWT tokens
出力:
```
feat(auth): implement JWT-based authentication

Add login endpoint and token validation middleware
```

**例2：**
入力: Fixed bug where dates displayed incorrectly in reports
出力:
```
fix(reports): correct date formatting in timezone conversion

Use UTC timestamps consistently across report generation
```

このスタイルに従ってください：type(scope): 簡潔な説明、その後詳細な説明。
```

例は、説明だけよりも望ましいスタイルと詳細レベルをClaudeが理解するのに役立ちます。
