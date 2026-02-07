#!/usr/bin/env python3
"""
スキルパッケージャー - スキルフォルダーの配布可能な.skillファイルを作成

使用方法:
    python utils/package_skill.py <path/to/skill-folder> [output-directory]

例:
    python utils/package_skill.py skills/public/my-skill
    python utils/package_skill.py skills/public/my-skill ./dist
"""

import sys
import zipfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from quick_validate import validate_skill


def package_skill(skill_path, output_dir=None):
    """
    スキルフォルダーを.skillファイルにパッケージ化。

    Args:
        skill_path: スキルフォルダーへのパス
        output_dir: .skillファイルのオプション出力ディレクトリ（デフォルトはカレントディレクトリ）

    Returns:
        作成された.skillファイルへのパス、エラーの場合はNone
    """
    skill_path = Path(skill_path).resolve()

    # Validate skill folder exists
    if not skill_path.exists():
        print(f"❌ エラー: スキルフォルダーが見つかりません: {skill_path}")
        return None

    if not skill_path.is_dir():
        print(f"❌ エラー: パスがディレクトリではありません: {skill_path}")
        return None

    # Validate SKILL.md exists
    skill_md = skill_path / "SKILL.md"
    if not skill_md.exists():
        print(f"❌ エラー: {skill_path} にSKILL.mdが見つかりません")
        return None

    # Run validation before packaging
    print("🔍 スキルを検証中...")
    valid, message = validate_skill(skill_path)
    if not valid:
        print(f"❌ 検証失敗: {message}")
        print("   パッケージ化する前に検証エラーを修正してください。")
        return None
    print(f"✅ {message}\n")

    # Determine output location
    skill_name = skill_path.name
    if output_dir:
        output_path = Path(output_dir).resolve()
        output_path.mkdir(parents=True, exist_ok=True)
    else:
        output_path = Path.cwd()

    skill_filename = output_path / f"{skill_name}.skill"

    # Create the .skill file (zip format)
    try:
        with zipfile.ZipFile(skill_filename, 'w', zipfile.ZIP_DEFLATED) as zipf:
            # Walk through the skill directory
            for file_path in skill_path.rglob('*'):
                if file_path.is_file():
                    # Calculate the relative path within the zip
                    arcname = file_path.relative_to(skill_path.parent)
                    zipf.write(file_path, arcname)
                    print(f"  追加: {arcname}")

        print(f"\n✅ スキルを正常にパッケージ化しました: {skill_filename}")
        return skill_filename

    except Exception as e:
        print(f"❌ .skillファイル作成エラー: {e}")
        return None


def main():
    if len(sys.argv) < 2:
        print("使用方法: python utils/package_skill.py <path/to/skill-folder> [output-directory]")
        print("\n例:")
        print("  python utils/package_skill.py skills/public/my-skill")
        print("  python utils/package_skill.py skills/public/my-skill ./dist")
        sys.exit(1)

    skill_path = sys.argv[1]
    output_dir = sys.argv[2] if len(sys.argv) > 2 else None

    print(f"📦 スキルをパッケージ化中: {skill_path}")
    if output_dir:
        print(f"   出力ディレクトリ: {output_dir}")
    print()

    result = package_skill(skill_path, output_dir)

    if result:
        sys.exit(0)
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
