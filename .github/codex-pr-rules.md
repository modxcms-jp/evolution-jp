# Codex Pull Request Rules for Evolution JP

When creating Pull Requests in this repository, follow these rules strictly.

## Pull Request creation

- Do not push directly to the main branch
- Always create a Pull Request

## PR title rules

- PR titles must be suitable for GitHub release notes
- Write PR titles in Japanese
- Titles must clearly describe the change
- Do not use vague expressions such as:
  - 更新
  - 修正対応
  - その他
- Good examples:
  - PHP 8.2 以降に対応
  - 管理画面に CSRF 対策を導入
  - データベース文字コードを utf8mb4 に移行
  - IE 向けレガシーコードを削除

## PR description rules

- Include a Summary section (1–2 sentences)
- Include a Notes section only if there are compatibility concerns
- Avoid long explanations

## Labeling rules

- Replace or add one of the following labels based on the change intent:
  - enhancement
  - bug
  - security
  - breaking-change
  - internal
- Do not use a label named `codex` for change classification

These rules are intended to ensure compatibility with GitHub auto-generated release notes.
