# Typecho Plugins Collection

面向 **Typecho 1.3.1-rc / PHP 8.2** 的插件分享集合：10 个社区插件的**二次开发维护快照**。
原作者与原始版权全部保留，本仓库只做兼容性、安全性与可维护性修补，不主张原作权利。

> 本项目是「二次开发」，不是原作者发布的版本。原作者信息见 [CREDITS.md](CREDITS.md)，
> 每个插件目录内另有 `二次开发说明.md` 记录本仓库的具体改动。
> 2026-11-05 整理、审查与修补。

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

- 插件目录内 **41 个 PHP 文件全部通过 PHP 8.2.35 `php -l`**（连同主题共 57 个文件一并校验）；
- Typecho 1.2+ 的自动加载器会把 `Typecho_*` / `Widget_*` 旧类名映射到命名空间类并补建
  `class_alias`，因此保留原插件的旧类名写法，不做大规模重写（降低引入回归的风险）；
- 保留原插件的 `@package / @author / @version / @link` 头注释，未做任何抹除。

## 审计工具

```bash
python tools/audit.py                 # 只读扫描，输出统计
python tools/audit.py --json report.json
python tools/audit.py --strict        # 高危项存在时返回非 0，可用于 CI
```

扫描项：动态执行 / 命令执行 / 不安全反序列化 / 变量包含 / 远程读取 / SQL 拼接 /
PHP 8 移除函数 / 请求参数直接输出 / 入口防护缺失 / 旧类名统计。

## 已知限制

- `MarkdownParse/vendor.phar` 是上游打包的第三方二进制依赖，本仓库未做内部代码审计，
  建议从上游 release 校验哈希后再使用。
- `Access` 等老插件在 PHP 8.2 下有动态属性 `Deprecated` 提示（官方镜像 `display_errors=Off`，
  不影响功能）；后续可逐步为相关类补 `#[AllowDynamicProperties]` 或显式声明属性。
- `XCaptcha/lib/class.geetestlib.php` 会向极验服务端发起请求，属于其正常功能；
  若不使用极验可不启用该驱动。
- 本集合未对插件做真实站点全功能回归（需要可用的 Typecho 实例），上线前请逐个启用验证。

## 许可

各插件**原代码**沿用其原作者许可（见各目录原有 LICENSE / README）；
本仓库新增的修补与文档采用 MIT，见 [LICENSE](LICENSE)。
如原作者希望移除或调整，请联系仓库维护者。
