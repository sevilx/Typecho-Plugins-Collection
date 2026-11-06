# Typecho Plugins Collection

面向 **Typecho 1.3.1-rc / PHP 8.2** 的插件分享集合：10 个社区插件的**二次开发维护快照**。
原作者与原始版权全部保留，本仓库只做兼容性、安全性与可维护性修补，不主张原作权利。

> 本项目是「二次开发」，不是原作者发布的版本。原作者信息见 [CREDITS.md](CREDITS.md)，
> 每个插件目录内另有 `二次开发说明.md` 记录本仓库的具体改动。
> 2026-11-05 整理、审查与修补；2026-11-07 在隔离 Typecho 1.3.1 实例上完成真实运行时回归，
> 修复了静态审查发现不了的「插件看起来启用了、实际不工作」问题。

## 兼容矩阵

| 插件 | 原作者 | 原版本 | 功能 | 本次状态 |
|---|---|---|---|---|
| [Access](Access/) | Kokororin | 2.1.0 | 访客日志与统计图表 | 已适配 1.3 / PHP 8.2 |
| [FuckAdComment](FuckAdComment/) | 原文件未标注作者 | 1.0.0 | 评论广告/垃圾内容过滤 | 已适配 1.3 / PHP 8.2 |
| [HelloWorld](HelloWorld/) | qining / Typecho 官方 | 1.0.0 | 官方示例插件 | 已适配 1.3 / PHP 8.2 |
| [MarkdownParse](MarkdownParse/) | mrgeneral | 2.4.3 | CommonMark / GFM 解析 | 已适配 1.3 / PHP 8.2 |
| [PageViews](PageViews/) | Pony | 1.0.1 | 文章浏览量统计 | 已适配 1.3 / PHP 8.2 |
| [PostToc](PostToc/) | 吴佳轶 | 1.4.1 | 文章目录 TOC | 已适配 1.3 / PHP 8.2 |
| [ShortLinks](ShortLinks/) | Ryan | 1.2.1 | 外链转 /go/ 内链 | 已适配 1.3 / PHP 8.2 |
| [Titleshow](Titleshow/) | 泽泽 | 1.1.2 | 标题展示特效 | 已适配 1.3 / PHP 8.2 |
| [TpCache](TpCache/) | 老高 | 0.7 | 页面缓存 | 已适配 1.3 / PHP 8.2 |
| [XCaptcha](XCaptcha/) | CairBin | 1.3.0 | 评论/登录验证码 | 已适配 1.3 / PHP 8.2 |

## 安装

1. 只复制**需要的**插件目录到 `usr/plugins/<插件名>/`（目录名必须与插件名一致）；
2. 后台「插件管理」启用，按各插件原 README 配置；
3. 启用前建议先备份数据库；`TpCache` 与 ESA / CDN 边缘缓存同时使用时，**只保留一层页面缓存**，
   否则主题更新后会出现「匿名访客看到旧主题页面、登录态看到新主题」的缓存分裂现象。

## 本次做了什么

### 安全修复（详见 [SECURITY.md](SECURITY.md)）

- **ShortLinks `/go/` 开放重定向 / SSRF**：新增 `isSafeRedirectTarget()`，
  拒绝非 http(s) 协议、控制字符、`localhost` / 单标签主机名、`127/10/172.16/192.168/169.254`
  等私有与保留网段、IPv6 字面量与进制变形 IP；16 组攻击/正常用例全部通过。
- **ShortLinks 任意文件读取**：跳转模板名改为 `[A-Za-z0-9_-]+` 白名单，缺文件时回退 `default.html`。
- **XSS**：注入跳转页的 `$target` 统一 `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`，延时强制转整数。
- **反序列化对象注入**：`ShortLinks` 与 `TpCache` 的 `unserialize()` 全部加 `allowed_classes => false`，
  并校验结果为数组。
- **直接访问防护**：8 个入口文件补齐 `__TYPECHO_ROOT_DIR__` 检查。

### 兼容性

- 仓库内 **42 个 PHP 文件全部通过 `php -l`**（插件目录 41 个 + `tools/shortlinks_test.php` 1 个；
  PHP 8.2.35 与 8.3.36 均校验通过，首轮还一并校验了主题 16 个文件）；
- Typecho 1.2+ 的自动加载器会把 `Typecho_*` / `Widget_*` 旧类名映射到命名空间类并补建
  `class_alias`，因此保留原插件的旧类名写法，不做大规模重写（降低引入回归的风险）；
- 保留原插件的 `@package / @author / @version / @link` 头注释，未做任何抹除。

## 运行时回归与修复（2026-11-07）

静态扫描查不出「插件已启用但前台没反应」。本轮在隔离的 **Typecho 1.3.1-rc + SQLite + PHP 8.3**
站点上逐个真实启用插件并请求页面，确认并修复了以下问题：

| 插件 / 位置 | 真实症状 | 修复 |
|---|---|---|
| **主题 `footer.php`** | 主题没有调用 `$this->footer()`，所有挂在 `Widget_Archive:footer` 的插件（Access 前端统计、PostToc 脚本、MarkdownParse）静默失效 | 在 `</body>` 前补 `$this->footer()`；此项属于 [Typecho-theme-DUX](https://github.com/sevilx/Typecho-theme-DUX) 仓库 |
| **PostToc** | 目录完全不出现：CSS 走 `header`，配置变量与 `script.js` 走 `footer` | 改为 `header` 一次性注入 CSS + 配置 + `defer script.js`，`footer` 保留兜底并去重；配置缺失时提供默认值 |
| **PostToc** | 深色模式白块、后台颜色写 inline 压过 CSS、目录在 1366/1440 宽遮挡标题 | 样式改为 CSS 变量 + `data-theme` / `prefers-color-scheme`；留白不足时自动降级为正文内 `.toc-inline` 卡片；配色只写 `--toc-*-user` |
| **ShortLinks** | 强力模式条件写反、自定义字段用了未定义 `$text`、`replace()` 只认双引号且误转相对链接/锚点/图片、白名单空行导致全放行 | 重写为 `preg_replace_callback()` + `isConvertibleLink()` / `isInternalUrl()`；强力模式跳过 `<a>/<pre>/<code>`/`](...)`；`target=_blank` 强制补 `rel="noopener noreferrer"` |
| **ShortLinks** | 评论者 URL 靠拼接 `'" target="_blank'` 实现，被 Typecho 1.3 的 `safeUrl()` 与主题转义破坏 | 删除 `authorPermalinkTarget` 配置，评论者链接只转 `/go/`，新窗口交回主题处理 |
| **PageViews** | 插件文件存在但未启用时，`class_exists()` 判断成立，`plugin('PageViews')` 抛异常导致全站 500 | 统计与输出都加 `try/catch (\Throwable)`，未启用时静默跳过 / 降级显示 0 |
| **Access** | `SHOW TABLES LIKE` 只兼容 MySQL；SQLite 探测不存在表时驱动输出 warning 印进页面 | 按 adapter 查 `sqlite_master` / `pg_tables` / `SHOW TABLES LIKE`，未知适配器降级并抑制 warning |
| **Titleshow** | PHP 8 `Undefined array key "hidden"` warning 插进 `<a href=...>`，冲坏最新评论挂件 | 数组/键/配置三重兜底，输出统一转义 |

验证结果：

```
HTTP 运行时回归         41 通过 / 0 失败（20 条路由 + 注入去重 + /go/ 安全 + 后台页面）
ShortLinks 纯逻辑单测    44 通过 / 0 失败
php -l (8.2.35 / 8.3.36) 42 个 PHP 文件，0 错误
node --check             PostToc/static/script.js 通过
只读安全审计             tools/audit.py --strict，0 高危 / 0 低危
只读冒烟测试             tools/smoke.py，15/15 PASS
真实浏览器渲染           Chromium/Edge 1440 浅色+深色、1920 悬浮、390 移动端
真实浏览器 DOM 断言       1440 → class="toc-inline"；1920 → position:fixed 悬浮且留白
```

## 工具

```bash
python tools/audit.py                 # 只读静态扫描，输出统计
python tools/audit.py --json report.json
python tools/audit.py --strict        # 高危项存在时返回非 0，可用于 CI
php tools/shortlinks_test.php        # ShortLinks 纯逻辑单测，无需安装 Typecho
python tools/smoke.py --base http://127.0.0.1:8901   # 只读运行时冒烟测试
```

`audit.py` 扫描项：动态执行 / 命令执行 / 不安全反序列化 / 变量包含 / 远程读取 / SQL 拼接 /
PHP 8 移除函数 / 请求参数直接输出 / 入口防护缺失 / 旧类名统计。

`smoke.py` 不登录、不写数据库、不改插件配置，检查首页与文章页无 PHP 报错、PostToc 注入去重、
`/go/` 对 `javascript:` / `localhost` / 内网 / 云元数据地址的拦截。未启用的插件会自动跳过。
在 Git Bash 下传绝对路径参数时可加 `MSYS_NO_PATHCONV=1`。

## 已知限制

- `MarkdownParse/vendor.phar` 是上游打包的第三方二进制依赖，本仓库未做内部代码审计，
  建议从上游 release 校验哈希后再使用。
- `Access` 等老插件在 PHP 8.2 下有动态属性 `Deprecated` 提示（官方镜像 `display_errors=Off`，
  不影响功能）；后续可逐步为相关类补 `#[AllowDynamicProperties]` 或显式声明属性。
- `XCaptcha/lib/class.geetestlib.php` 会向极验服务端发起请求，属于其正常功能；
  若不使用极验可不启用该驱动。
- 运行时回归是在隔离的 Typecho 1.3.1-rc + **SQLite** + DUX 主题实例上完成的；
  生产环境若是 MySQL / PostgreSQL 或其他主题，建议先跑 `tools/smoke.py` 再逐个启用确认。
- 前台插件依赖主题调用 `$this->header()` 与 `$this->footer()`。换主题后若目录、统计脚本不出现，
  先检查这两个插件点，而不是先怀疑插件本身。
- 浏览器实渲染使用 Chromium/Edge headless 完成；用户指定的 RunningCheeseFirefox 157 定制版
  在已有实例占用时无法拉起无头截图/调试端口，因此未出 Firefox 截图。所用 Web API
  （`AbortController.signal`、`:is()`、`Element.closest()`、`scrollbar-width`、CSS 变量与 `min()`）
  均为 Firefox 已支持的标准特性。

## 许可

各插件**原代码**沿用其原作者许可（见各目录原有 LICENSE / README）；
本仓库新增的修补与文档采用 MIT，见 [LICENSE](LICENSE)。
如原作者希望移除或调整，请联系仓库维护者。
