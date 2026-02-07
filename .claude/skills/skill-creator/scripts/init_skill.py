#!/usr/bin/env python3
"""
スキル初期化ツール - テンプレートから新しいスキルを作成

使用方法:
    init_skill.py <skill-name> --path <path>

例:
    init_skill.py my-new-skill --path skills/public
    init_skill.py my-api-helper --path skills/private
    init_skill.py custom-skill --path /custom/location
"""

import sys
from pathlib import Path


SKILL_TEMPLATE = """---
name: {skill_name}
description: [TODO: スキルが何をするか＋いつ使用するかのトリガー条件を日本語で記述]
---

# {skill_title}

[TODO: 1-2文の概要]
コーディング規約は `AGENTS.md` を参照。

## コマンド

### [TODO: コマンド名]
[TODO: ステップを記述]

[TODO: SKILL.mdは500行未満に保ち、詳細はreferences/に分離する。不要なサンプルディレクトリは削除する。]
"""

EXAMPLE_SCRIPT = '''#!/usr/bin/env python3
"""
{skill_name} - サンプルスクリプト。実装に置き換えるか、不要なら削除。
"""

def main():
    # TODO: 実装
    pass

if __name__ == "__main__":
    main()
'''

EXAMPLE_REFERENCE = """# {skill_title} リファレンス

[TODO: SKILL.mdに含めるには長い詳細情報をここに記述。不要なら削除。]
"""

EXAMPLE_ASSET = ""  # アセットは実際のファイルで置き換える。プレースホルダー不要。


def title_case_skill_name(skill_name):
    """ハイフン区切りのスキル名をタイトルケースに変換して表示用にする。"""
    return ' '.join(word.capitalize() for word in skill_name.split('-'))


def init_skill(skill_name, path):
    """
    テンプレートSKILL.mdで新しいスキルディレクトリを初期化。

    Args:
        skill_name: スキルの名前
        path: スキルディレクトリを作成するパス

    Returns:
        作成されたスキルディレクトリへのパス、エラーの場合はNone
    """
    # Determine skill directory path
    skill_dir = Path(path).resolve() / skill_name

    # Check if directory already exists
    if skill_dir.exists():
        print(f"❌ エラー: スキルディレクトリは既に存在します: {skill_dir}")
        return None

    # Create skill directory
    try:
        skill_dir.mkdir(parents=True, exist_ok=False)
        print(f"✅ スキルディレクトリを作成しました: {skill_dir}")
    except Exception as e:
        print(f"❌ ディレクトリ作成エラー: {e}")
        return None

    # Create SKILL.md from template
    skill_title = title_case_skill_name(skill_name)
    skill_content = SKILL_TEMPLATE.format(
        skill_name=skill_name,
        skill_title=skill_title
    )

    skill_md_path = skill_dir / 'SKILL.md'
    try:
        skill_md_path.write_text(skill_content)
        print("✅ SKILL.mdを作成しました")
    except Exception as e:
        print(f"❌ SKILL.md作成エラー: {e}")
        return None

    # Create resource directories with example files
    try:
        # Create scripts/ directory with example script
        scripts_dir = skill_dir / 'scripts'
        scripts_dir.mkdir(exist_ok=True)
        example_script = scripts_dir / 'example.py'
        example_script.write_text(EXAMPLE_SCRIPT.format(skill_name=skill_name))
        example_script.chmod(0o755)
        print("✅ scripts/example.pyを作成しました")

        # Create references/ directory with example reference doc
        references_dir = skill_dir / 'references'
        references_dir.mkdir(exist_ok=True)
        example_reference = references_dir / 'api_reference.md'
        example_reference.write_text(EXAMPLE_REFERENCE.format(skill_title=skill_title))
        print("✅ references/api_reference.mdを作成しました")

        # Create assets/ directory (empty - populate with actual files)
        assets_dir = skill_dir / 'assets'
        assets_dir.mkdir(exist_ok=True)
        print("✅ assets/を作成しました")
    except Exception as e:
        print(f"❌ リソースディレクトリ作成エラー: {e}")
        return None

    # Print next steps
    print(f"\n✅ スキル '{skill_name}' を {skill_dir} に正常に初期化しました")
    print("\n次のステップ:")
    print("1. SKILL.mdを編集してTODO項目を完了し、descriptionを更新")
    print("2. scripts/、references/、assets/のサンプルファイルをカスタマイズまたは削除")
    print("3. スキル構造を確認する準備ができたらバリデーターを実行")

    return skill_dir


def main():
    if len(sys.argv) < 4 or sys.argv[2] != '--path':
        print("使用方法: init_skill.py <skill-name> --path <path>")
        print("\nスキル名の要件:")
        print("  - ハイフンケース識別子（例: 'data-analyzer'）")
        print("  - 小文字、数字、ハイフンのみ")
        print("  - 最大40文字")
        print("  - ディレクトリ名と正確に一致する必要がある")
        print("\n例:")
        print("  init_skill.py my-new-skill --path skills/public")
        print("  init_skill.py my-api-helper --path skills/private")
        print("  init_skill.py custom-skill --path /custom/location")
        sys.exit(1)

    skill_name = sys.argv[1]
    path = sys.argv[3]

    print(f"🚀 スキルを初期化中: {skill_name}")
    print(f"   場所: {path}")
    print()

    result = init_skill(skill_name, path)

    if result:
        sys.exit(0)
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
