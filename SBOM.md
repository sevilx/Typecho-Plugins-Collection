# 软件物料清单（SBOM）

> 生成日期：2026-11-07 ｜ 自动生成，勿手工编辑。
> 覆盖范围：`Typecho-theme-DUX/` 与 `plugins/` 下的第三方压缩资产、二进制与 phar 内 Composer 依赖。
> 用途：依赖扫描输入、供应链事件定位、部署前完整性校验。**本清单不是安全审计结论。**

## 1. vendor.phar 自身

| 文件 | 大小 (B) | SHA-256 |
|---|---:|---|
| `plugins/MarkdownParse/vendor.phar` | 3578841 | `0023ee698431855d5238482d35e7fdf8e533b4aec99d6f3f8dd35b26400d69ba` |

phar 带 SHA-256 自签名；自签名只能证明文件未被改动，**不能证明来源可信**。部署前请比对本表中的哈希。

## 2. phar 内 Composer 依赖（30 个）

| 包名 | 版本 | 来源 |
|---|---|---|
| `clue/phar-composer` | `v1.4.0` | https://github.com/clue/phar-composer.git |
| `dflydev/dot-access-data` | `v3.0.3` | https://github.com/dflydev/dflydev-dot-access-data.git |
| `doctrine/inflector` | `2.0.10` | https://github.com/doctrine/inflector.git |
| `guzzlehttp/guzzle` | `7.9.2` | https://github.com/guzzle/guzzle.git |
| `guzzlehttp/promises` | `2.0.3` | https://github.com/guzzle/promises.git |
| `guzzlehttp/psr7` | `2.7.0` | https://github.com/guzzle/psr7.git |
| `knplabs/packagist-api` | `v1.7.2` | https://github.com/KnpLabs/packagist-api.git |
| `league/commonmark` | `2.6.1` | https://github.com/thephpleague/commonmark.git |
| `league/config` | `v1.2.0` | https://github.com/thephpleague/config.git |
| `nette/schema` | `v1.3.2` | https://github.com/nette/schema.git |
| `nette/utils` | `v4.0.5` | https://github.com/nette/utils.git |
| `psr/container` | `2.0.2` | https://github.com/php-fig/container.git |
| `psr/event-dispatcher` | `1.0.0` | https://github.com/php-fig/event-dispatcher.git |
| `psr/http-client` | `1.0.3` | https://github.com/php-fig/http-client.git |
| `psr/http-factory` | `1.1.0` | https://github.com/php-fig/http-factory.git |
| `psr/http-message` | `2.0` | https://github.com/php-fig/http-message.git |
| `ralouphie/getallheaders` | `3.0.3` | https://github.com/ralouphie/getallheaders.git |
| `simonvomeyser/commonmark-ext-lazy-image` | `v2.0.3` | https://github.com/simonvomeyser/commonmark-ext-lazy-image.git |
| `symfony/console` | `v6.4.10` | https://github.com/symfony/console.git |
| `symfony/deprecation-contracts` | `v3.5.1` | https://github.com/symfony/deprecation-contracts.git |
| `symfony/finder` | `v6.4.10` | https://github.com/symfony/finder.git |
| `symfony/polyfill-ctype` | `v1.30.0` | https://github.com/symfony/polyfill-ctype.git |
| `symfony/polyfill-intl-grapheme` | `v1.30.0` | https://github.com/symfony/polyfill-intl-grapheme.git |
| `symfony/polyfill-intl-normalizer` | `v1.30.0` | https://github.com/symfony/polyfill-intl-normalizer.git |
| `symfony/polyfill-mbstring` | `v1.30.0` | https://github.com/symfony/polyfill-mbstring.git |
| `symfony/polyfill-php80` | `v1.31.0` | https://github.com/symfony/polyfill-php80.git |
| `symfony/process` | `v6.4.14` | https://github.com/symfony/process.git |
| `symfony/service-contracts` | `v3.5.0` | https://github.com/symfony/service-contracts.git |
| `symfony/string` | `v6.4.10` | https://github.com/symfony/string.git |
| `wnx/commonmark-mark-extension` | `v1.3.0` | https://github.com/stefanzweifel/commonmark-mark-extension.git |

> `clue/phar-composer`、`symfony/console`、`symfony/process`、`knplabs/packagist-api` 等为**构建期工具**，
> 被打进运行时包属于多余执行面，后续应改用 `composer.json` + `composer.lock` 重新构建。

## 3. 第三方压缩资产与二进制（16 个）

| 文件 | 大小 (B) | SHA-256 |
|---|---:|---|
| `Typecho-theme-DUX/css/bootstrap.min.css` | 109518 | `b5fd723750763ebb731f9221e413e7d64d58d5192dc040e42292ed3dcccca732` |
| `Typecho-theme-DUX/css/bootstrap.min.js` | 31819 | `24cc29533598f962823c4229bc280487646a27a42a95257c31de1b9b18f3710f` |
| `Typecho-theme-DUX/css/highlight.min.js` | 45919 | `f9b8554da46627f734ede57cd2753c803d9c041174ad891fd6dc6a5e5adbbdbd` |
| `Typecho-theme-DUX/css/html5shiv.min.js` | 2730 | `dc9cbf19b48bae0d28f72e59e67d6ec34ab1644087ec2e8e42954180d1586b48` |
| `Typecho-theme-DUX/css/jquery.min.js` | 92594 | `89acccdd96eaf7d22d8ebfe514fedf2076b2e72d4e9e260d61dcbe44e39f7079` |
| `Typecho-theme-DUX/css/pace.min.js` | 12507 | `10fae43631849825b26b36f703f1298fe5bb426da907dbe77d7c3e5fa2c898f3` |
| `Typecho-theme-DUX/font-awesome/css/font-awesome.min.css` | 31018 | `6f14101998fff51d94efe7f1946d812be542fc3f97b7306ddc116eaeca8fcf7f` |
| `Typecho-theme-DUX/js/libs/hammer.min.js` | 18358 | `cb6509483b0d2b5af7d52f117f0d3f8b2a2e56eaaeeb61258ca72d338875eaf1` |
| `Typecho-theme-DUX/js/libs/ias.min.js` | 5049 | `e0d39b9072c31de8557cac44b6c76fe3f6aec72fee06ebaac4d00e1f9e45286f` |
| `Typecho-theme-DUX/js/libs/jquery.cookie.min.js` | 2267 | `9b68a7e1b4753c9671aedd210a427f040818604e7d410a08c71b45811509ab73` |
| `Typecho-theme-DUX/js/libs/jsrender.min.js` | 14732 | `3d1aeb21118a9698a97acb66a1d5f5df9acb91fed11675c29909722513e2a786` |
| `Typecho-theme-DUX/js/libs/lazyload.min.js` | 4070 | `7d57bb6491a9562a031a93b529e5a96e8741a5994549c5069a0bbe91fda74957` |
| `Typecho-theme-DUX/js/libs/router.min.js` | 9991 | `aa33b71b9b50730189f6540f07ce7296c841d7295eac8e40fe646e615920eb05` |
| `plugins/Access/lib/ipipfree.ipdb` | 3649897 | `b82b874152c798dda407ffe7544e1f5ec67efa1f5c334efc0d3893b8053b4be1` |
| `plugins/MarkdownParse/vendor.phar` | 3578841 | `0023ee698431855d5238482d35e7fdf8e533b4aec99d6f3f8dd35b26400d69ba` |
| `plugins/XCaptcha/static/altcha.min.js` | 53064 | `0b1935db178b68a90b773ce52026aba588aef23b67aabcaca2aab57c00cd6493` |

## 4. 已知缺口（需部署侧处理）

- 无 `composer.lock` / `package-lock.json`，本表由 phar 内 `installed.json` 反推，版本以运行时包为准。
- 压缩 JS 无 SRI；外部 CDN 资源无本地副本，无法计算 `integrity`，需本地化后补齐。
- `jquery 1.9.1` / `bootstrap 3.2.0` 已停止维护，升级会破坏 DUX 主题，需单独排期并全量回归。
- 本表不含 Typecho 核心及生产镜像内的系统依赖。
