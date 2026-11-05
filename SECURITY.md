# 安全审查报告

- 审查日期：2026-11-05
- 范围：10 个插件目录、41 个 PHP 文件、约 12,000 行 PHP/JS
- 方法：只读静态扫描 + PHP 8.2.35 语法检查 + 关键函数单元实测
- 工具：`tools/audit.py`（可重复运行）

## 结论

未发现后门、隐藏上报、远程脚本注入或挖矿代码。发现并修复 **4 类真实风险**，
另标注 3 项需使用者注意的限制。

| 级别 | 问题 | 位置 | 处理 |
|---|---|---|---|
| 高 | `/go/<base64>` 开放重定向可跳 `localhost:8080` / `crowdsec:8080` 等内网地址 | `ShortLinks/Action.php` | 新增 `isSafeRedirectTarget()` 协议 + 主机白名单，非法目标重定向回首页 |
| 高 | 跳转模板名可拼接 `../` 造成任意文件读取 | `ShortLinks/Action.php` | 模板名白名单 + `is_file()` + 回退默认模板 |
| 中 | `unserialize()` 未限制 `allowed_classes`，畸形/恶意序列化串可触发对象注入 | `ShortLinks/Action.php`、`TpCache/Plugin.php` | 全部加 `array('allowed_classes' => false)` 并校验返回数组 |
| 中 | 跳转页把目标 URL 原样写进 HTML（XSS），延时值未转型 | `ShortLinks/Action.php` | `htmlspecialchars(ENT_QUOTES)` + `(int)` |
| 低 | 8 个入口文件缺少 `__TYPECHO_ROOT_DIR__` 防护 | 各 `Plugin.php` / `Action.php` / `panel.php` | 已补齐 |

## 验证

```
isSafeRedirectTarget() 用例：16/16 PASS
php -l（PHP 8.2.35）：57 个文件（插件 41 + 主题 16），0 错误
```

用例覆盖：合法外链 3 组；`127.0.0.1` / `localhost` / `crowdsec` / `192.168.*` / `10.*` /
`169.254.169.254` / `2130706433` / `0x7f000001` / `[::1]` / `javascript:` / `//` /
`ftp:` / CRLF 头注入共 13 组攻击输入，全部拒绝。

## 复核过的「疑似」项（非漏洞）

- `Access/Access_Logs.php` 的 `$preset` 用 `array_key_exists($preset, $presetWheres)` 白名单校验后才拼 SQL，安全；
  其余查询均使用 `where(..., ?)` 参数绑定。
- `Access/Access_Migration.php` 的 `SHOW TABLES LIKE '{$prefix}access'` 中 `$prefix` 来自数据库配置，非用户输入。
- `XCaptcha/lib/class.geetestlib.php` 的 `file_get_contents($url)` 是极验官方 SDK 的服务端校验请求，目标固定。
- 未发现 `eval` / `assert` / `create_function` / 命令执行 / 变量 `include` / 请求参数直接 echo。

## 仍需使用者注意

1. `MarkdownParse/vendor.phar` 为第三方打包依赖，未做内部二进制审计。
2. 老插件在 PHP 8.2 下有动态属性 `Deprecated` 提示（不致命）。
3. `TpCache` 与边缘缓存（阿里云 ESA / CDN）叠加会造成缓存分裂：主题或插件更新后，
   匿名访客可能长时间看到旧页面。建议只保留一层页面缓存，并在发布后主动刷新边缘缓存。
