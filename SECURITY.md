# セキュリティポリシー

## 脆弱性の報告

脆弱性を見つけたときは、公開の Issue・Pull Request・フォーラムには書かず、GitHub の非公開の報告窓口から知らせてください。

1. このリポジトリの [Security](https://github.com/modxcms-jp/evolution-jp/security) タブを開く
2. 「Report a vulnerability」から報告を作る

報告には、次のことを書いてください。分かる範囲でかまいません。

- 対象の版（例: `1.2.3J`）と、PHP・データベースの版
- 影響（何ができてしまうか）
- 再現の手順
- 直し方の案（あれば）

## 報告を受けたあとの流れ

1. 報告を受け取ったことを、報告の画面で返信します
2. 内容を確かめ、修正が要るかを判断して、報告の画面で知らせます
3. 修正は非公開で進め、修正版のリリースと同時にセキュリティアドバイザリを公開します
4. 報告してくださった方は、希望があればアドバイザリに協力者として記載します

修正版が出るまでは、内容を公開しないようお願いします。

## サポートする版

| 版 | セキュリティ修正 |
| --- | --- |
| 1.3 系（開発中の `main`） | 対象 |
| 1.2 系の最新版 | 対象 |
| 1.2 系の古い版（1.2.0J など） | 対象外 |
| 1.1 系以前 | 対象外 |

1.2 系の古い版を使っている場合は 1.2 系の最新版へ、できれば 1.3 系へアップデートしてください。1.1 系以前を使っている場合も、サポートする版へのアップデートをおすすめします。アップデート手順は [modx.jp](https://modx.jp/update.html) を参照してください。

## Reporting a vulnerability (English)

Please do not report security vulnerabilities through public issues. Use [GitHub's private vulnerability reporting](https://github.com/modxcms-jp/evolution-jp/security/advisories/new) instead. Reports in English are welcome.
