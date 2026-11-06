<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 一个可以随意拖动的目录，右下角可以选择显示或隐藏。<br>
 * 
 * 详细说明及插件更新，请查阅： <a target="_blank" href="https://github.com/HelloWuJiaYi/PostToc/" rel="noopener noreferrer">PostToc</a> 
 * 
 * @package PostToc
 * @version 1.4.1
 * @author 吴佳轶
 * @link https://www.wujiayi.vip
 */

class PostToc_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 单次请求内是否已注入资源。
     *
     * 原版把 CSS 挂在 header 钩子、把配置与 script.js 挂在 footer 钩子。
     * 但 Typecho 只会在主题显式调用 $this->footer() 时触发 footer 钩子，
     * 不少主题（含 DUX 1.4.0 之前）的 footer.php 没有这一句，结果目录
     * 样式加载了、脚本却永远不加载，表现为「插件完全不起作用」。
     *
     * 现在两个钩子都注册、共用一段注入逻辑并用此标记去重：
     * 无论主题调用 header、footer 还是两者都调用，资源都恰好输出一次。
     * 同时保留 footer 钩子，兼容旧版本已写进数据库 handles 的站点。
     */
    private static $assetsInjected = false;

    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->header = array('PostToc_Plugin', 'renderHeader');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('PostToc_Plugin', 'renderFooter');
    }

    public static function deactivate(){}

public static function config(Typecho_Widget_Helper_Form $form)
    {
        
        $defaultDisplay = new Typecho_Widget_Helper_Form_Element_Radio(
            'defaultDisplay', 
            array('1' => '是', '0' => '否'),
            '1', 
            _t('默认显示目录'),  
            _t('加载文章页面时，是否显示目录。')
        );
        $form->addInput($defaultDisplay);

        // 手机端目录按钮显示设置
        $mobileDisplay = new Typecho_Widget_Helper_Form_Element_Radio(
            'mobileDisplay', 
            array('1' => '显示', '0' => '隐藏'),
            '1', 
            _t('手机端显示目录按钮'), 
            _t('选择是否在手机端显示目录按钮。')
        );
        $form->addInput($mobileDisplay);
        
        $offset = new Typecho_Widget_Helper_Form_Element_Text(
            'offset', 
            NULL, 
            '10', 
            _t('滚动偏移量'),  
            _t('设置文章标题距离页面导航栏的偏移量，以像素为单位，避免被动态导航栏遮挡。')
        );
        $form->addInput($offset);

        // 新增目录上下位置偏移量设置
        $verticalOffset = new Typecho_Widget_Helper_Form_Element_Text(
            'verticalOffset', 
            NULL, 
            '0', 
            _t('目录垂直位置偏移量'),  
            _t('负数向上移动，正数向下移动，以像素为单位。')
        );
        $form->addInput($verticalOffset);
    
        // 新增目录左右位置偏移量设置
        $horizontalOffset = new Typecho_Widget_Helper_Form_Element_Text(
            'horizontalOffset', 
            NULL, 
            '0', 
            _t('目录水平位置偏移量'),  
            _t('负数向左移动，正数向右移动，以像素为单位。')
        );
        $form->addInput($horizontalOffset);
    
        $textColor = new Typecho_Widget_Helper_Form_Element_Text(
            'textColor', 
            NULL, 
            '#777777', 
            _t('目录项文字颜色'),  
            _t('输入十六进制颜色代码，例如：#777777。')
        );
        $form->addInput($textColor);
        
        $activeTextColor = new Typecho_Widget_Helper_Form_Element_Text(
            'activeTextColor', 
            NULL, 
            '#bc6462', 
            _t('选中状态文字颜色'),  
            _t('输入十六进制颜色代码，例如：#bc6462。')
        );
        $form->addInput($activeTextColor);
        
        $backgroundColor = new Typecho_Widget_Helper_Form_Element_Text(
            'backgroundColor', 
            NULL, 
            '#C8C8C870', 
            _t('目录项背景色块颜色（含透明度）'),  
            _t('输入十六进制颜色代码，包括透明度，例如：#C8C8C870。最后两位表示透明度，00 为全透明，FF 为不透明。')
        );
        $form->addInput($backgroundColor);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /** 目录只在文章页显示，独立页面不显示 */
    private static function isArticlePage()
    {
        $widget = Typecho_Widget::widget('Widget_Archive');
        return $widget->is('single') && !$widget->is('page');
    }

    /**
     * 读取后台配置并回落默认值。
     * 老站点升级后新字段可能为空，直接 json_encode(null) 会让脚本里的
     * Number(verticalOffset) 变成 NaN，目录定位失效。
     */
    private static function settings()
    {
        $defaults = array(
            'defaultDisplay'   => array('defaultDisplay', '1'),
            'mobileDisplay'    => array('mobileDisplay', '1'),
            'navbarOffset'     => array('offset', '10'),
            'verticalOffset'   => array('verticalOffset', '0'),
            'horizontalOffset' => array('horizontalOffset', '0'),
            'textColor'        => array('textColor', '#777777'),
            'activeTextColor'  => array('activeTextColor', '#bc6462'),
            'backgroundColor'  => array('backgroundColor', '#C8C8C870'),
        );

        $config = null;
        try {
            $config = Typecho_Widget::widget('Widget_Options')->plugin('PostToc');
        } catch (\Throwable $e) {
            // 插件刚复制进目录、尚未在后台启用时配置不存在，用默认值即可
        }

        $out = array();
        foreach ($defaults as $jsName => $item) {
            $value = $config ? $config->{$item[0]} : null;
            $out[$jsName] = ($value === null || $value === '') ? $item[1] : (string) $value;
        }
        return $out;
    }

    /** 统一注入 CSS、配置与脚本 */
    private static function renderAssets()
    {
        if (self::$assetsInjected) {
            return;
        }
        self::$assetsInjected = true;

        $base = htmlspecialchars(
            rtrim((string) Helper::options()->pluginUrl, '/') . '/PostToc',
            ENT_QUOTES,
            'UTF-8'
        );
        // 后台可填颜色值，HEX_* 防止管理员输入 </script> 提前闭合脚本块
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

        echo '<link rel="stylesheet" href="' . $base . '/static/style.css">' . "\n";
        echo '<script>';
        foreach (self::settings() as $jsName => $value) {
            $json = json_encode($value, $flags);
            echo 'var ' . $jsName . ' = ' . ($json === false ? '""' : $json) . ';';
        }
        echo '</script>' . "\n";
        // defer 脚本在 DOM 解析完成后、DOMContentLoaded 之前执行：
        // 既能拿到上面的全局变量，也不阻塞首屏渲染。
        echo '<script defer src="' . $base . '/static/script.js"></script>' . "\n";
    }

    public static function renderHeader()
    {
        if (self::isArticlePage()) {
            self::renderAssets();
        }
    }

    public static function renderFooter()
    {
        // 兜底：极少数主题不调用 header() 只调用 footer()
        if (!self::$assetsInjected && self::isArticlePage()) {
            self::renderAssets();
        }
    }
}
