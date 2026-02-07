#!/usr/bin/env python3
"""
スキルのクイック検証スクリプト - 外部依存なし
"""

import sys
import re
from pathlib import Path


def parse_frontmatter(text):
    """regexベースのYAML frontmatterパーサー（PyYAML不要）"""
    match = re.match(r'^---\n(.*?)\n---', text, re.DOTALL)
    if not match:
        return None, "frontmatterの形式が無効です"

    result = {}
    for line in match.group(1).splitlines():
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        # key: value 形式をパース（value内の : は許容）
        kv_match = re.match(r'^([a-zA-Z_-]+)\s*:\s*(.*)', line)
        if kv_match:
            key = kv_match.group(1).strip()
            value = kv_match.group(2).strip()
            result[key] = value

    return result, None


def validate_skill(skill_path):
    """スキルの基本的な検証"""
    skill_path = Path(skill_path)

    # Check SKILL.md exists
    skill_md = skill_path / 'SKILL.md'
    if not skill_md.exists():
        return False, "SKILL.mdが見つかりません"

    # Read and validate frontmatter
    content = skill_md.read_text()
    if not content.startswith('---'):
        return False, "YAML frontmatterが見つかりません"

    # Parse frontmatter
    frontmatter, error = parse_frontmatter(content)
    if error:
        return False, error
    if not frontmatter:
        return False, "FrontmatterはYAML辞書である必要があります"

    # Define allowed properties
    ALLOWED_PROPERTIES = {'name', 'description', 'license', 'allowed-tools', 'metadata'}

    # Check for unexpected properties
    unexpected_keys = set(frontmatter.keys()) - ALLOWED_PROPERTIES
    if unexpected_keys:
        return False, (
            f"SKILL.md frontmatterに予期しないキー: {', '.join(sorted(unexpected_keys))}。 "
            f"許可されたプロパティ: {', '.join(sorted(ALLOWED_PROPERTIES))}"
        )

    # Check required fields
    if 'name' not in frontmatter:
        return False, "frontmatterに'name'がありません"
    if 'description' not in frontmatter:
        return False, "frontmatterに'description'がありません"

    # Extract name for validation
    name = frontmatter.get('name', '')
    if not isinstance(name, str):
        return False, f"Nameは文字列である必要があります、{type(name).__name__}が渡されました"
    name = name.strip()
    if name:
        # Check naming convention (hyphen-case: lowercase with hyphens)
        if not re.match(r'^[a-z0-9-]+$', name):
            return False, f"Name '{name}'はハイフンケースである必要があります（小文字、数字、ハイフンのみ）"
        if name.startswith('-') or name.endswith('-') or '--' in name:
            return False, f"Name '{name}'はハイフンで始まる/終わる、または連続するハイフンを含むことはできません"
        # Check name length (max 64 characters per spec)
        if len(name) > 64:
            return False, f"Nameが長すぎます（{len(name)}文字）。最大64文字です。"

    # Extract and validate description
    description = frontmatter.get('description', '')
    if not isinstance(description, str):
        return False, f"Descriptionは文字列である必要があります、{type(description).__name__}が渡されました"
    description = description.strip()
    if description:
        # Check for angle brackets
        if '<' in description or '>' in description:
            return False, "Descriptionに山括弧（< または >）を含めることはできません"
        # Check description length (max 1024 characters per spec)
        if len(description) > 1024:
            return False, f"Descriptionが長すぎます（{len(description)}文字）。最大1024文字です。"

    return True, "スキルは有効です！"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("使用方法: python quick_validate.py <skill_directory>")
        sys.exit(1)

    valid, message = validate_skill(sys.argv[1])
    print(message)
    sys.exit(0 if valid else 1)
