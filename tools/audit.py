#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Typecho 插件集合只读安全审计。

用法：
    python tools/audit.py
    python tools/audit.py --json report.json
    python tools/audit.py --strict          # 存在高危项时返回 1，可用于 CI

只读、不联网、不修改任何文件。扫描插件目录（tools/ 的上一级）下全部 PHP 文件。
"""
import argparse
import collections
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
SKIP_DIRS = {'tools', '.git', '__pycache__'}

# 高危：出现即视为需要人工确认/修复
CRITICAL = {
    '动态代码执行': r'\b(eval|assert|create_function)\s*\(',
    '命令执行': r'\b(system|exec|shell_exec|passthru|proc_open|popen)\s*\(',
    '变量文件包含': r'\b(include|require)(_once)?\s*\(\s*\$',
    '请求参数直出': r'echo\s+\$_(GET|POST|REQUEST|COOKIE)',
    '废弃 mysql 扩展': r'\bmysql_(connect|query|fetch_\w+)\s*\(',
    'PHP8 已移除函数': r'\b(each|ereg|eregi|split|create_function|get_magic_quotes_gpc)\s*\(',
    'PHP8 implode 反序': r'implode\s*\(\s*\$[A-Za-z_]\w*\s*,',
}

# 需要关注但不一定致命
WARN = {
    '远程/变量读取': r'file_get_contents\s*\(\s*(?:\$|[\'\"]https?://)',
    'SQL 字符串拼接': r'(?:SELECT|INSERT|UPDATE|DELETE)\s[^;]*?\.\s*\$',
    '反序列化': r'\bunserialize\s*\(',
    'base64 动态解码': r'eval\s*\(\s*base64_decode',
}

OLD_CLASS = re.compile(
    r'\b(Typecho_(?:Widget|Db|Common|Plugin|Cookie|Request|Response)[A-Za-z_]*'
    r'|Widget_\w+|Typecho_Widget_Helper_Form_Element_\w+)')


def read(path):
    raw = path.read_bytes()
    for enc in ('utf-8', 'gb18030'):
        try:
            return raw.decode(enc)
        except UnicodeDecodeError:
            pass
    return raw.decode('utf-8', 'replace')


def php_files():
    for p in sorted(ROOT.rglob('*.php')):
        if any(part in SKIP_DIRS for part in p.relative_to(ROOT).parts):
            continue
        yield p


def unserialize_guarded(text):
    """unserialize() 必须带 allowed_classes => false；否则算高危。"""
    bad = []
    for m in re.finditer(r'\bunserialize\s*\(', text):
        segment = text[m.start():m.start() + 220]
        # 简单地看这一段里有没有 allowed_classes
        if 'allowed_classes' not in segment:
            line = text.count('\n', 0, m.start()) + 1
            bad.append(line)
    return bad


def entry_guards():
    """检查各插件入口文件是否有 __TYPECHO_ROOT_DIR__ 防护。"""
    missing = []
    for d in sorted(p for p in ROOT.iterdir() if p.is_dir() and p.name not in SKIP_DIRS):
        for name in ('Plugin.php', 'Action.php', 'panel.php'):
            f = d / name
            if f.exists() and '__TYPECHO_ROOT_DIR__' not in read(f)[:700]:
                missing.append(str(f.relative_to(ROOT)))
    return missing


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--json', metavar='PATH', help='把结果写成 JSON')
    ap.add_argument('--strict', action='store_true', help='存在高危项时返回 1')
    args = ap.parse_args()

    findings = []
    per_plugin = collections.defaultdict(lambda: collections.Counter())
    old_names = collections.Counter()
    total_files = 0

    for p in php_files():
        total_files += 1
        text = read(p)
        rel = str(p.relative_to(ROOT))
        plugin = p.relative_to(ROOT).parts[0]
        old_names.update(OLD_CLASS.findall(text))
        for label, pattern in CRITICAL.items():
            for m in re.finditer(pattern, text, re.I):
                line = text.count('\n', 0, m.start()) + 1
                findings.append({'level': 'HIGH', 'item': label,
                                 'file': rel, 'line': line,
                                 'snippet': text.splitlines()[line - 1].strip()[:120]})
                per_plugin[plugin][label] += 1
        for label, pattern in WARN.items():
            for m in re.finditer(pattern, text, re.I | re.S):
                line = text.count('\n', 0, m.start()) + 1
                per_plugin[plugin][label] += 1
        for line in unserialize_guarded(text):
            findings.append({'level': 'HIGH', 'item': '反序列化未限制 allowed_classes',
                             'file': rel, 'line': line, 'snippet': ''})
            per_plugin[plugin]['反序列化未限制 allowed_classes'] += 1

    guards = entry_guards()
    for f in guards:
        findings.append({'level': 'LOW', 'item': '入口缺少 ROOT_DIR 防护',
                         'file': f, 'line': 0, 'snippet': ''})

    high = [x for x in findings if x['level'] == 'HIGH']
    report = {
        'root': '<repository root>',
        'php_files': total_files,
        'plugins': len([p for p in ROOT.iterdir() if p.is_dir() and p.name not in SKIP_DIRS]),
        'high': len(high),
        'low': len(guards),
        'findings': findings,
        'per_plugin': {k: dict(v) for k, v in per_plugin.items()},
        'legacy_class_references': sum(old_names.values()),
        'legacy_class_kinds': len(old_names),
    }

    print('=== Typecho 插件只读安全审计 ===')
    print('PHP 文件 %d | 插件 %d | 高危 %d | 低危 %d'
          % (report['php_files'], report['plugins'], report['high'], report['low']))
    print('旧类名引用 %d 次 / %d 种（Typecho 1.2+ 自动映射，非错误）'
          % (report['legacy_class_references'], report['legacy_class_kinds']))
    for item in findings:
        print('  [%-4s] %-28s %s:%s' % (item['level'], item['item'],
                                        item['file'], item['line']))
    if not findings:
        print('  无高危与低危发现。')
    if args.json:
        pathlib.Path(args.json).write_text(
            json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
        print('JSON ->', args.json)
    return 1 if (args.strict and high) else 0


if __name__ == '__main__':
    sys.exit(main())
