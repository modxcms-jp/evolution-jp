# Evolution CMS JP Edition

[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D%207.4-8892bf.svg)](https://www.php.net/)
[![Downloads](https://img.shields.io/badge/download-modx.jp-success.svg)](https://modx.jp/download.html)
[![Docs](https://img.shields.io/badge/docs-evolution--cms--jp-orange.svg)](https://modx.jp/documents.html)

- [Evolution CMS JP Edition](#evolution-cms-jp-edition)
  - [特長](#特長)
  - [ディレクトリ構成](#ディレクトリ構成)
  - [動作要件](#動作要件)
  - [インストール手順](#インストール手順)
  - [サポートとリソース](#サポートとリソース)
  - [ライセンス](#ライセンス)

## 特長

- **ツリー構造でのページ管理**: リソースは階層ツリーで整理され、公開順序や親子関係を一覧から把握できます。ページ全体を俯瞰しながら更新できる点が Evolution CMS の大きな魅力です。([チュートリアル抜粋](https://modx.jp/docs/tutorial/quick.html))
- **軽快なレスポンスとキャッシュ**: 軽量なコアとファイルベースのキャッシュにより、静的出力に匹敵するスピードでコンテンツを配信します。管理画面操作も軽く、更新作業がストレスになりません。([チュートリアル抜粋](https://modx.jp/docs/tutorial/quick.html))
- **DocumentParser 中心のアーキテクチャ**: `index.php` から `DocumentParser` がリクエストの初期化、テンプレート解析、イベント発火、レスポンス生成までを担当します。フローの詳細は `assets/docs/architecture.md` を参照してください。
- **柔軟なテンプレートシステム**: テンプレート、チャンク、スニペット、プレースホルダーを組み合わせてサイトを構築できます。解析順序やモディファイアは `assets/docs/template-system.md` に整理されています。
- **プラグインイベントと拡張性**: `OnWebPageInit` から `OnWebPageComplete` までのイベントで自由に拡張でき、`assets/docs/events-and-plugins.md` にライフサイクルをまとめています。

## ディレクトリ構成

| ディレクトリ | 概要 |
| --- | --- |
| `assets/` | テンプレート、スニペット、プラグイン、マネージャリソースなどのアセット類と、コアアーキテクチャやキャッシュ機構などの技術ドキュメント（`assets/docs/`）を格納します。|
| `content/` | デフォルトのメディアルートです。`rb_base_dir` の設定に応じて画像やファイルが配置されます。|
| `install/` | セットアップスクリプトです。新規インストールやアップデートで使用します。|
| `manager/` | 管理画面とコア PHP クラスの実装です。`DocumentParser` や拡張 API が含まれます。|
| `temp/` | キャッシュ、バックアップ、エクスポート済み静的 HTML などを格納する一時ディレクトリです。|

## 動作要件

- Web サーバー (Apache / Nginx など)
- PHP 7.4 以上（8.2 以降を推奨）
- PHP の拡張: mbstring（必須）、mysqli（必須）、gd（推奨。無いと、ログイン時の画像認証（CAPTCHA）など画像まわりの処理に制限があります。`php evo.php health:check` も確認します）、exif（推奨。無いと、ファイルピッカーのサムネイルと大きさに写真の向きを反映しません）
- MySQL 互換データベース (MySQL 5.7 以上 / MariaDB など)
- アップデートできるのは 1.0.0J 以降のサイトです。それより古い DB（上流の古い Evolution など）からは、そのままではアップデートできません

インストーラは、入口で mbstring の有無を確かめ、無ければ案内を出して止まります。続くインストール前の状態確認では、セッション、キャッシュ・`temp/`・`content/` の書き込み権限、`manager/includes/config.inc.php` を書き込めるかを確かめ、1 つでも失敗すると先へ進めません。接続した DB のバージョンも表示します。

## インストール手順

1. リリースアーカイブを展開し、Web サーバーのドキュメントルートに配置します。
2. `install/install-config.sample.php` を同じディレクトリに `install-config.php` としてコピーします。インストーラへのアクセスを許可する IP アドレス（`allowed_ips`）、または BASIC 認証（`basic_auth`）を設定します。両方を空にするとアクセス制御は無効になります（非推奨）。このファイルが無いと、インストーラは先へ進みません。
3. ブラウザで `/install/` にアクセスし、ウィザードの指示に従ってセットアップを進めます。
4. インストール完了後はセキュリティのために `install/` ディレクトリを削除します。
5. 管理画面にログインし、必要なシステム設定やユーザー権限を整備してください。

サンプルテンプレートやデモコンテンツも同梱されているため、初期セットアップ直後からサイト構造や MODX タグの利用方法を確認できます。

## サポートとリソース

- 日本語ポータル: [modx.jp](https://modx.jp/)
- ドキュメントハブ: [assets/docs/](assets/docs/)
- イベント・ニュース: [modx.jp/news.html](https://modx.jp/news.html)
- フォーラム: [MODX Japan Users Group](https://forum.modx.jp/)
- アップデート手順: [modx.jp/docs/update.html](https://modx.jp/docs/update.html)

## ライセンス

Evolution CMS JP Edition は GNU General Public License (GPL) に基づいて配布されています。詳細は各ソースファイルヘッダーおよびインストールパッケージに含まれるライセンス文をご確認ください。
