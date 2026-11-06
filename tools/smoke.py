#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""只读运行时冒烟测试（不需要登录、不写数据库、不改插件配置）。

用法：
    python tools/smoke.py --base http://127.0.0.1:8901
    python tools/smoke.py --base https://blog.example.com --article /archives/10/

前提：
  - 目标站点是 Typecho 1.2/1.3，且至少有一篇已发布文章；
  - 已启用 PostToc / ShortLinks 时，脚本会自动检查它们的注入与 /go/ 安全；
    未启用的插件对应检查会跳过，不算失败。
  - 主题必须在 </body> 前调用 $this->footer()，否则 footer 钩子的插件不会生效。

依赖：requests（pip install requests）
"""

import argparse
import base64
import re
import sys
from urllib.parse import urlparse

import requests

ERROR_PAT = re.compile(
    r'Fatal error|Parse error|Uncaught |<b>Warning</b>|<b>Notice</b>|'
    r'<b>Deprecated</b>|Typecho\\Plugin\\Exception|Stack trace:', re.I)

# 这些目标一旦被 /go/ 放行，就说明开放重定向 / SSRF 防线失效
EVIL_TARGETS = [
    b'javascript:alert(1)',
    b'http://localhost/admin',
    b'http://127.0.0.1/admin',
    b'http://192.168.1.1/',
    b'http://169.254.169.254/latest/meta-data/',
    b'http://[::1]/',
]


def key_for(url):
    """与 ShortLinks_Plugin::urlSafeB64Encode() 一致：URL-safe base64 去 padding。"""
    return base64.urlsafe_b64encode(url).decode('ascii').rstrip('=')


def main():
    ap = argparse.ArgumentParser(description='Typecho 插件集合只读运行时冒烟测试')
    ap.add_argument('--base', default='http://127.0.0.1:8901', help='站点根地址')
    ap.add_argument('--article', default=None, help='文章页路径；缺省自动从首页发现')
    ap.add_argument('--timeout', type=int, default=20)
    args = ap.parse_args()

    base = args.base.rstrip('/')

    # Git Bash / MSYS 会把以 / 开头的命令行参数改写成本地 Windows 路径
    # （例如 /archives/10/ -> C:/.../PortableGit/archives/10/），这里还原。
    if args.article and re.match(r'^[A-Za-z]:[/\\]', args.article):
        p = args.article.replace('\\', '/')
        for marker in ('/index.php/', '/archives/', '/post/', '/posts/'):
            if marker in p:
                args.article = p[p.index(marker):]
                break
        else:
            print('无法识别 --article 路径：%s' % args.article)
            return 2

    session = requests.Session()
    passed = 0
    failures = []

    def ok(name, cond, detail=''):
        nonlocal passed
        if cond:
            passed += 1
            print('  PASS ', name)
        else:
            failures.append((name, detail))
            print('  FAIL ', name, '::', str(detail)[:300])

    def get(path, **kw):
        return session.get(base + path, timeout=args.timeout,
                           allow_redirects=False, **kw)

    print('== 1. 站点可用性 ==')
    try:
        home = get('/')
    except Exception as e:
        print('无法连接站点:', e)
        return 2
    ok('首页无 5xx', home.status_code < 500, home.status_code)
    ok('首页无 PHP 错误/告警', not ERROR_PAT.search(home.text))

    article_path = args.article
    if not article_path:
        # 从首页链接里找第一篇文章；DUX 与默认主题都能命中
        hrefs = re.findall(r'href="([^"]+)"', home.text)
        candidates = [h for h in hrefs
                      if re.search(r'/(?:archives|posts?)/\d|\.html?(?:\?|$)', h)
                      and 'feed' not in h and 'comment' not in h]
        for h in dict.fromkeys(candidates):
            # 主题常输出绝对 URL，统一转成站点内路径
            if h.startswith('http://') or h.startswith('https://'):
                parsed = urlparse(h)
                if parsed.netloc.lower() != urlparse(base).netloc.lower():
                    continue
                h = parsed.path + (('?' + parsed.query) if parsed.query else '')
            if not h.startswith('/'):
                continue
            r = get(h)
            if r.status_code < 400 and re.search(r'<h[1-6]\b', r.text):
                article_path = h
                break
    if not article_path:
        print('没有找到文章页，请用 --article 指定路径')
        return 2
    print('  文章页:', article_path)

    article = get(article_path)
    ok('文章页无 PHP 错误/告警', not ERROR_PAT.search(article.text),
       article.text[:200])
    html = article.text

    print('== 2. PostToc 注入（未启用则跳过） ==')
    if '/PostToc/static/' not in html:
        print('  SKIP  PostToc 未启用或主题未调用 $this->header()')
    else:
        ok('CSS / JS / 配置各只注入一次',
           html.count('/PostToc/static/style.css') == 1
           and html.count('/PostToc/static/script.js') == 1
           and html.count('var defaultDisplay') == 1)
        ok('script.js 使用 defer', bool(re.search(
            r'<script defer src="[^"]*PostToc/static/script\.js"', html)))
        css_url = re.search(r'href="([^"]*PostToc/static/style\.css)"', html)
        js_url = re.search(r'<script defer src="([^"]*PostToc/static/script\.js)"', html)
        for label, m in (('style.css', css_url), ('script.js', js_url)):
            if not m:
                ok('%s 可访问' % label, False, '未找到 URL')
                continue
            u = m.group(1)
            p = u[u.find('/usr/plugins/'):] if '/usr/plugins/' in u else u
            r = get(p)
            ok('%s 200' % label, r.status_code == 200, r.status_code)

    print('== 3. ShortLinks /go/ 安全（未启用则跳过） ==')
    go_paths = ['/go/{k}/', '/index.php/go/{k}/']
    probe = None
    for tpl in go_paths:
        r = get(tpl.format(k='AAAA'))
        if r.status_code in (301, 302, 200, 404):
            probe = (tpl, r)
            break
    if probe is None:
        print('  SKIP  /go/ 路由不可用（ShortLinks 未启用）')
    else:
        tpl = probe[0]
        ok('/go/ 非法 key 不会 5xx', probe[1].status_code < 500,
           probe[1].status_code)
        for target in EVIL_TARGETS:
            r = get(tpl.format(k=key_for(target)),
                    headers={'Referer': base + article_path})
            loc = r.headers.get('Location') or ''
            parsed = urlparse(loc.strip())
            site_host = urlparse(base).netloc.lower()
            # 回落到本站首页 / 本站路径是预期行为，不算开放重定向
            same_site = (not parsed.netloc) or parsed.netloc.lower() == site_host
            if parsed.scheme and parsed.scheme not in ('http', 'https'):
                unsafe = True
            elif same_site:
                unsafe = False
            else:
                unsafe = any(x in loc.lower() for x in (
                    'localhost', '127.0.0.1', '192.168.', '169.254.', '[::1]'))
            ok('%s 被拦截' % target.decode('utf-8'),
               r.status_code < 500 and not unsafe,
               '%s %s' % (r.status_code, loc))

        # 正文里已转换的外链必须补 rel，避免 target=_blank 反向 tabnabbing
        converted = [m for m in re.findall(r'<a\b[^>]*>', html) if '/go/' in m]
        if converted:
            ok('转换后的外链都带 noopener',
               all('noopener' in m for m in converted), converted[:2])
        else:
            print('  SKIP  该文章没有站外链接')

    print('\n结果: %d 通过, %d 失败' % (passed, len(failures)))
    for name, detail in failures:
        print('  -', name, '::', str(detail)[:400])
    return 1 if failures else 0


if __name__ == '__main__':
    sys.exit(main())
