# 原作者与署名

本仓库是**二次开发**集合。所有插件的原始代码、设计与版权归各自原作者所有；
本仓库仅在原作基础上做兼容性、安全性与可维护性修补，并完整保留原文件内的署名注释。

| 插件 | 原作者 | 原仓库 / 主页 | 原版本 | 备注 |
|---|---|---|---|---|
| Access | Kokororin | https://github.com/kokororin/typecho-plugin-Access | 2.1.0 | 部分优化重构：一名宅 https://zhaiyiming.com/ |
| FuckAdComment | 原文件未标注作者 | （原仓库信息缺失） | 1.0.0 | 文件内无 @author/@link，若知悉原作者请补充 |
| HelloWorld | qining / Typecho 官方 | http://typecho.org | 1.0.0 | Typecho 自带示例，仅作结构参考 |
| MarkdownParse | mrgeneral | https://github.com/mrgeneralgoo/typecho-markdown | 2.4.3 | 基于 league/commonmark，含 vendor.phar 二进制依赖 |
| PageViews | Pony | http://blog.ponycool.com | 1.0.1 | — |
| PostToc | 吴佳轶 | https://www.wujiayi.vip | 1.4.1 | — |
| ShortLinks | Ryan | https://github.com/benzBrake/ShortLinks | 1.2.1 | 本次重点安全修复对象 |
| Titleshow | 泽泽 | https://github.com/jrotty/Titleshow | 1.1.2 | — |
| TpCache | 老高 | http://www.phpgao.com | 0.7 | 本次反序列化加固对象 |
| XCaptcha | CairBin | https://github.com/CairBin/XCaptcha | 1.3.0 | 贡献者 yumusb https://github.com/yumusb；致敬 noisky、scenery |

## 致谢

- 感谢以上原作者与贡献者开源 / 分享这些插件。
- Typecho 项目：https://github.com/typecho/typecho
- 特别说明：`HelloWorld` 为 Typecho 官方示例插件；`FuckAdComment` 源文件内未找到作者信息，
  若您知道原作者，欢迎提 issue 补充，我们会立即更正署名。

## 二次开发的边界

- 未抹除任何原 `@author` / `@link` / `@version` 注释；
- 未把任何插件改名为自有品牌；
- 未移除原 LICENSE 文件；
- 新增的安全补丁均在注释里标注「安全加固」，便于与原作区分。
