# XCaptcha-for-Typecho

用于Typecho博客系统的插件，为你的博客评论、注册、登陆添加验证码。


## 支持

目前支持的验证码如下：

* [hCaptcha](https://www.hcaptcha.com)
* [Cloudflare Turnstile](https://www.cloudflare.com)
* [Google reCaptcha v2](https://developers.google.cn/recaptcha/docs/display?hl=zh-cn)
* [极验证Geetest v3](https://www2.geetest.com)
* [alcha-org](https://altcha.org/docs/get-started/)


## 使用方式

### 下载本项目

通过git命令来获取本项目：

```sh
git clone https://github.com/CairBin/XCaptcha.git
```

或者通过下载的方式获取压缩包，解压后的文件夹名称修改为`XCaptcha`。

### 配置插件

将`XCaptcha`文件夹上传到你的Typecho博客的`usr/plugins/`目录下，在Typecho后台的插件面板里激活即可。

从上述所支持验证码的官网注册账户，然后获取你站点的`Captcha ID`和`Secret Key`，有的也叫`Site Key`和`Secret Key`，本质上都一样，对应公钥和私钥。前者用于前端标识可以公开，后者用于服务端向验证码服务器校验需要保密。

修改你主题模板的`comments.php`文件，主题目录是`usr/themes/your_theme/`，在评论提交按钮之前或者表单最后添加一行代码，然后回到博客后台配置插件，将获取到的ID/Key填写进去，以及配置其他参数即可。

```php
<?php if (array_key_exists('XCaptcha', Typecho_Plugin::export()['activated'])) : XCaptcha_Plugin::showCaptcha(); endif; ?>


```

如果你想要禁用评论提交按钮，经过验证后才可点击提交表单，只需要给按钮添加类`custom-submit-button`即可，以默认主题为例：

```php
<button type="submit" class="custom-submit-button submit"><?php _e('提交评论'); ?></button>
```


部分主题可能不存在`comments.php`文件，这时候你需要从其他文件里找到评论表单，并在合适的位置添加这行代码。

此外你还需要注意以下几点：

* “验证码JS地址”取决于网络情况，它会替代原本引入的验证初始化脚本（不是二次验证的接口），遇到某些JS加载缓慢时使用，**如果不明白留空即可**。“校验地址”用于更换服务端验证接口，留空使用默认地址，一般在默认接口无法访问或者失效时填写。

* 区分JQuery CDN URL与上述部分，JQuery CDN URL用于通过CDN引入JQuery（不能留空），默认使用jsdelivr，而上述部分是用于**初始化/校验**验证码。

* XCaptcha v1.1.0版本极验证加载不再依赖JQuery，启用JQuery与JQuery CDN URL项已经被移除。另外此版本兼容Typecho 1.1版本。

* 如果勾选了开启登陆页面验证码则，注册页面也会跟着开启，前提是你启动了注册功能。

## 插件导致无法登陆后台

登陆页面启用验证码，如果配置不当，会导致验证一直失败而无法进入博客后台。

如果遇到这种问题，请修改Typecho的数据库的`typecho_options`表中，`name`属性为`plugins`的那一行的值为`a:0:{}`以禁用所有插件。

**非常重要：在进行此操作前请务必备份数据库！**

## 关于v1.3.0及后续版本

v1.3.0版本添加了渲染组件成功以及校验成功的回调函数，用于**禁用/启用按钮**以及提示**验证码组件正在加载**，因此对于`hcaptcha`、`cloudflare`以及`recaptcha`在配置`cdnUr;l`的时候需要设置`onload`参数为`beforeCheckCallback`，例如`https://hcaptcha.com/1/api.js?onload=beforeCheckCallback`

更具体的参数配置并不属于本文范畴，你应当参考所使用验证码的官方文档。

## 反馈

反馈请提交仓库issue，声明Typecho版本号、插件版本号、所用主题等信息，最好给出演示站点。


## 项目展示

![登陆界面](/img/login_page.png)

![插件配置](/img/plugin_config.png)

![评论区](/img/comment_area.png)


## 特别感谢

本项目在编写时参考了其他插件项目：

* [noisky/typecho-plugin-geetest](https://github.com/noisky/typecho-plugin-geetest)
* [scenery/typecho-plugins/CaptchPlus](https://github.com/scenery/typecho-plugins)
