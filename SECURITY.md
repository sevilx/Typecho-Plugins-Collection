# 安全审查报告

- 审查日期：2026-11-05（静态）；2026-11-07（运行时回归复核）
- 范围：10 个插件目录（41 个插件 PHP 文件）+ `tools/shortlinks_test.php`，约 12,000 行 PHP/JS
- 方法：只读静态扫描 + PHP 8.2.35 / 8.3.36 语法检查 + 关键函数单元实测 +
  隔离 Typecho 1.3.1 站点真实启用回归 + 浏览器实渲染
- 工具：`tools/audit.py`、`tools/shortlinks_test.php`、`tools/smoke.py`（均可重复运行）

## 结论

未发现后门、隐藏上报、远程脚本注入或挖矿代码。首轮静态审查发现并修复 **4 类真实风险**，
第二轮运行时回归又定位并修复 **7 类输出转义 / 容错 / 兼容性问题**（见下表），
另标注 3 项需使用者注意的限制。

| 级别 | 问题 | 位置 | 处理 |
|---|---|---|---|
| 高 | `/go/<base64>` 开放重定向可跳 `localhost:8080` / `crowdsec:8080` 等内网地址 | `ShortLinks/Action.php` | 新增 `isSafeRedirectTarget()` 协议 + 主机白名单，非法目标重定向回首页 |
| 高 | 跳转模板名可拼接 `../` 造成任意文件读取 | `ShortLinks/Action.php` | 模板名白名单 + `is_file()` + 回退默认模板 |
| 中 | `unserialize()` 未限制 `allowed_classes`，畸形/恶意序列化串可触发对象注入 | `ShortLinks/Action.php`、`TpCache/Plugin.php` | 全部加 `array('allowed_classes' => false)` 并校验返回数组 |
| 中 | 跳转页把目标 URL 原样写进 HTML（XSS），延时值未转型 | `ShortLinks/Action.php` | `htmlspecialchars(ENT_QUOTES)` + `(int)` |
| 低 | 8 个入口文件缺少 `__TYPECHO_ROOT_DIR__` 防护 | 各 `Plugin.php` / `Action.php` / `panel.php` | 已补齐 |

### 2026-11-07 运行时复核追加

| 级别 | 问题 | 位置 | 处理 |
|---|---|---|---|
| 中 | 跳转页模板占位符 `{{title}}` / `{{siteUrl}}` / `{{logoUrl}}` 原样输出，落在 HTML 属性里可破坏标签 | `ShortLinks/Action.php` | `__relpaceCallback()` 统一 `htmlspecialchars(ENT_QUOTES)` |
| 中 | 评论者 URL 靠拼接 `'" target="_blank'` 实现，经 `Common::safeUrl()` 与主题转义后产生坏链接 | `ShortLinks/Plugin.php` | 删除该配置项，评论者链接只转 `/go/` |
| 中 | 后台颜色值直接写进 `<script>` 全局字符串，可能闭合脚本标签 | `PostToc/Plugin.php` | 配置输出使用 `JSON_HEX_TAG / AMP / APOS / QUOT`，JS 侧再校验颜色格式 |
| 中 | 正文外链被改写时只加 `target="_blank"` 不补 `rel`，存在反向 tabnabbing | `ShortLinks/Plugin.php` | 自动补 `rel="noopener noreferrer"`，已有 `rel` 不重复 |
| 低 | 白名单末尾空行使 `strpos($url, '')` 恒命中，导致「不转换域名」全量放行 | `ShortLinks/Plugin.php` | `textareaToArr()` 丢弃空行，`checkDomain()` 跳过空条目 |
| 低 | 插件未启用时 `plugin('PageViews')` 抛异常，被主题 `class_exists()` 命中后造成全站 500 | `PageViews/Plugin.php` | `try/catch (\Throwable)` 降级 |
| 低 | 主题侧的 `Titleshow` 输出未转义提示文字 / URL | `Titleshow/Plugin.php` | 统一 `htmlspecialchars(ENT_QUOTES)` + 键兜底 |

## 验证

```
isSafeRedirectTarget() 用例：16/16 PASS
ShortLinks 逻辑单测：44/44 PASS
HTTP 运行时回归：41/41 PASS（20 条路由 + /go/ 攻击输入 + 后台页面 + 注入去重）
php -l（PHP 8.2.35 / 8.3.36）：仓库 42 个 PHP 文件，0 错误
                            （首轮另校验主题 16 个，合计 58 个）
tools/audit.py --strict：0 高危 / 0 低危，退出码 0
tools/smoke.py：15/15 PASS（显式文章页）、10/10 PASS（自动发现）
```

用例覆盖：合法外链 3 组；`127.0.0.1` / `localhost` / `crowdsec` / `192.168.*` / `10.*` /
`169.254.169.254` / `2130706433` / `0x7f000001` / `[::1]` / `javascript:` / `//` /
`ftp:` / CRLF 头注入共 13 组攻击输入，全部拒绝。

## 复核过的「疑似」项（非漏洞）

- `Access/Access_Logs.php` 的 `$preset` 用 `array_key_exists($preset, $presetWheres)` 白名单校验后才拼 SQL，安全；
  其余查询均使用 `where(..., ?)` 参数绑定。
- `Access/Access_Migration.php` 的表存在性探测按数据库适配器分别查 `sqlite_master` / `pg_tables` /
  `SHOW TABLES LIKE`；表名由 `getPrefix()` 拼接并经 `/^[A-Za-z0-9_]+$/` 校验，不含用户输入。
  2026-11-07 起不再用 `SELECT 1 FROM <可能不存在的表>` 做探测，避免 SQLite 驱动把 warning 印进页面。
  `audit_report.json` 里 `Access: SQL 字符串拼接: 2` 指的就是这两条系统目录表查询，
  属于需人工确认的 WARN 提示，不是漏洞（`findings` 为空、`high/low` 均为 0）。
- `XCaptcha/lib/class.geetestlib.php` 的 `file_get_contents($url)` 是极验官方 SDK 的服务端校验请求，目标固定。
- 未发现 `eval` / `assert` / `create_function` / 命令执行 / 变量 `include` / 请求参数直接 echo。

## 仍需使用者注意

1. `MarkdownParse/vendor.phar` 为第三方打包依赖，未做内部二进制审计。
2. 老插件在 PHP 8.2 下有动态属性 `Deprecated` 提示（不致命）。
3. `TpCache` 与边缘缓存（阿里云 ESA / CDN）叠加会造成缓存分裂：主题或插件更新后，
   匿名访客可能长时间看到旧页面。建议只保留一层页面缓存，并在发布后主动刷新边缘缓存。
