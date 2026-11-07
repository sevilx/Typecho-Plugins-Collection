<?php

namespace TypechoPlugin\MarkdownParse;
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }


require_once 'phar://' . __DIR__ . '/vendor.phar/MarkdownParse.php';

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Widget\Options;

/**
 * 符合 CommonMark 和 GFM（GitHub-Flavored Markdown）规范的 Markdown 解析插件，强大而丰富的功能助你在不同平台上展现一致的出色
 *
 * @author  mrgeneral
 * @package MarkdownParse
 * @version 2.4.3
 * @link    https://www.chengxiaobai.cn/
 */
class Plugin implements PluginInterface
{
    const RADIO_VALUE_DISABLE = 0;
    const RADIO_VALUE_AUTO    = 1;
    const RADIO_VALUE_FORCE   = 2;

    const CDN_SOURCE_DEFAULT = 'baomitu';
    // 固定版本号，消除 latest / 无版本漂移；部署后应对实际文件生成 SRI 并本地化。
    const CDN_SOURCE_MERMAID = [
        'jsDelivr' => 'https://cdn.jsdelivr.net/npm/mermaid@10.7.0/dist/mermaid.esm.min.mjs',
        'cdnjs'    => 'https://cdnjs.cloudflare.com/ajax/libs/mermaid/10.7.0/mermaid.esm.min.mjs',
        'baomitu'  => 'https://lib.baomitu.com/mermaid/10.7.0/mermaid.esm.min.mjs'
    ];
    const CDN_SOURCE_MATHJAX = [
        'jsDelivr' => 'https://cdn.jsdelivr.net/npm/mathjax@3.2.2/es5/tex-mml-chtml.js',
        'cdnjs'    => 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-chtml.js',
        'baomitu'  => 'https://lib.baomitu.com/mathjax/3.2.2/es5/tex-mml-chtml.js'
    ];

    public static function activate()
    {
        // 文章与评论分档：评论可匿名提交，必须转义原始 HTML。
        \Typecho\Plugin::factory('\Widget\Base\Contents')->markdown = [__CLASS__, 'parseContent'];
        \Typecho\Plugin::factory('\Widget\Base\Comments')->markdown = [__CLASS__, 'parseComment'];
        \Typecho\Plugin::factory('Widget_Archive')->footer          = [__CLASS__, 'resourceLink'];
    }

    public static function deactivate()
    {
        // TODO: Implement deactivate() method.
    }

    public static function config(Form $form)
    {
        $elementToc = new Form\Element\Radio('is_available_toc', [self::RADIO_VALUE_DISABLE => _t('不解析'), self::RADIO_VALUE_AUTO => _t('解析')], self::RADIO_VALUE_AUTO, _t('是否解析 [TOC] 语法（符合 HTML 规范，无需 JS 支持）'), _t('开会后支持 [TOC] 语法来生成目录'));
        $form->addInput($elementToc);

        $elementMermaid = new Form\Element\Radio('is_available_mermaid', [self::RADIO_VALUE_DISABLE => _t('不开启'), self::RADIO_VALUE_AUTO => _t('开启（按需加载）'), self::RADIO_VALUE_FORCE => _t('开启（每次加载，pjax 主题建议选择此选项）')], self::RADIO_VALUE_AUTO, _t('是否开启 Mermaid 支持（支持自动识别，按需渲染，无需担心引入冗余资源）'), _t('开启后支持解析并渲染 <a href="https://mermaid-js.github.io/mermaid/#/">Mermaid</a>'));
        $form->addInput($elementMermaid);

        $elementMermaidTheme = new Form\Element\Radio('mermaid_theme', ['default' => _t('默认（default）'), 'neutral' => _t('墨水（neutral）'), 'dark' => _t('暗黑（dark）'), 'forest' => _t('森林绿（forest）')], 'default', _t('Mermaid 主题颜色'), _t('可以去这里 <a href="https://mermaid.live/edit">实时编辑器</a>调整主题配置看下效果'));
        $form->addInput($elementMermaidTheme);

        $elementMathJax = new Form\Element\Radio('is_available_mathjax', [self::RADIO_VALUE_DISABLE => _t('不开启'), self::RADIO_VALUE_AUTO => _t('开启（按需加载）'), self::RADIO_VALUE_FORCE => _t('开启（每次加载，pjax 主题建议选择此选项）')], self::RADIO_VALUE_AUTO, _t('是否开启 MathJax 支持（支持自动识别，按需渲染，无需担心引入冗余资源）'), _t('开启后支持解析并渲染 <a href="https://www.mathjax.org/">MathJax</a>'));
        $form->addInput($elementMathJax);

        $elementCDNSource = new Form\Element\Radio('cdn_source', array_combine(array_keys(self::CDN_SOURCE_MERMAID), array_map('_t', array_keys(self::CDN_SOURCE_MERMAID))), self::CDN_SOURCE_DEFAULT, _t('静态资源 CDN'), _t('jsDelivr 默认使用最新版本'));
        $form->addInput($elementCDNSource);

        $elementInternalHosts = new Form\Element\Text('internal_hosts', null, '', _t('设置内部链接'), _t('默认为本站点地址，支持正则表达式("/(^|\.)example\.com$/")，多个可用英文逗号分隔。<br/>外部链接解析策略：默认在新窗口中打开，并加上 "noopener noreferrer" 属性'));
        $form->addInput($elementInternalHosts);

        $elementHelper = new Form\Element\Radio('show_help_info', [], self::RADIO_VALUE_DISABLE, _t('<a href="https://www.chengxiaobai.cn/php/markdown-parser-library.html/">点击查看更新信息</a>'), _t('<a href="https://www.chengxiaobai.cn/record/markdown-concise-grammar-manual.html/">点击查看语法手册</a>'));
        $form->addInput($elementHelper);
    }

    public static function personalConfig(Form $form)
    {
        // TODO: Implement personalConfig() method.
    }

    /** 正文：作者可控，保留原始 HTML 以兼容 phar 内 LaTeX 的 <div> 注入。 */
    public static function parseContent($text, $widget = null, $lastResult = null)
    {
        return self::render($text, $lastResult, false);
    }

    /** 评论：匿名可提交，原始 HTML 全部转义，杜绝存储型 XSS。 */
    public static function parseComment($text, $widget = null, $lastResult = null)
    {
        return self::render($text, $lastResult, true);
    }

    private static function render($text, $lastResult, bool $strict)
    {
        // Typecho filter 链把上一个回调结果放在末位参数，兼容单参数与多参数调用。
        if ($lastResult !== null && $lastResult !== '') {
            $text = $lastResult;
        }
        $text = is_scalar($text) ? (string) $text : '';

        // phar 内 getConfig() 未覆盖这三项，CommonMark 2.6.1 默认
        // html_input=ALLOW / allow_unsafe_links=true / max_nesting_level=PHP_INT_MAX。
        $config = array(
            'html_input'         => $strict ? 'escape' : 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => $strict ? 100 : 200,
        );

        $markdownParser = MarkdownParse::getInstance();
        $markdownParser->setIsTocEnable(
            !$strict && (bool) (Options::alloc()->plugin('MarkdownParse')->is_available_toc ?? false)
        );
        $internalHosts = (string) (Options::alloc()->plugin('MarkdownParse')->internal_hosts ?? '');
        $markdownParser->setInternalHosts(
            $internalHosts !== '' ? $internalHosts : (string) parse_url(Options::alloc()->siteUrl, PHP_URL_HOST)
        );

        return $markdownParser->parse($text, $config);
    }

    public static function resourceLink()
    {
        $markdownParser     = MarkdownParse::getInstance();
        $configMermaid      = (int)Options::alloc()->plugin('MarkdownParse')->is_available_mermaid;
        $configLaTex        = (int)Options::alloc()->plugin('MarkdownParse')->is_available_mathjax;
        $configCDN          = (string)Options::alloc()->plugin('MarkdownParse')->cdn_source;
        $isAvailableMermaid = $configMermaid === self::RADIO_VALUE_FORCE || ($markdownParser->getIsNeedMermaid() && $configMermaid === self::RADIO_VALUE_AUTO);
        $isAvailableMathjax = $configLaTex   === self::RADIO_VALUE_FORCE || ($markdownParser->getIsNeedLaTex() && $configLaTex === self::RADIO_VALUE_AUTO);

        $resourceContent  = '';

        if ($isAvailableMermaid) {
            $mermaidCdn = self::CDN_SOURCE_MERMAID[$configCDN] ?? self::CDN_SOURCE_MERMAID[self::CDN_SOURCE_DEFAULT];
            $resourceContent .= sprintf('<script type="module">import mermaid from "%s";', htmlspecialchars($mermaidCdn, ENT_QUOTES, 'UTF-8'));
            $theme = (string) (Options::alloc()->plugin('MarkdownParse')->mermaid_theme ?? 'default');
            if (!in_array($theme, array('default', 'neutral', 'dark', 'forest'), true)) {
                $theme = 'default';
            }
            $themeJson = json_encode($theme, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $resourceContent .= sprintf('mermaid.initialize({ startOnLoad: true,theme:%s});</script>', $themeJson === false ? '"default"' : $themeJson);
        }

        if ($isAvailableMathjax) {
            $resourceContent .= '<script type="text/javascript">(function(){MathJax={loader: {load: [\'[tex]/gensymb\']},tex:{inlineMath:[[\'$\',\'$\'],[\'\\\\(\',\'\\\\)\']],packages: {\'[+]\': [\'gensymb\']}}}})();</script>';
            // 不再加载运行时第三方 polyfill：MathJax 3 / 现代浏览器无需，且构成供应链入口。
            $mathCdn = self::CDN_SOURCE_MATHJAX[$configCDN] ?? self::CDN_SOURCE_MATHJAX[self::CDN_SOURCE_DEFAULT];
            $resourceContent .= sprintf('<script id="MathJax-script" defer type="text/javascript" src="%s"></script>', htmlspecialchars($mathCdn, ENT_QUOTES, 'UTF-8'));
        }

        echo $resourceContent;
    }
}
