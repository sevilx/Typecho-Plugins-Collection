<?php
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }


/**
 * 文章浏览量统计
 *
 * @package PageViews
 * @author Pony
 * @version 1.0.1
 * @link http://blog.ponycool.com
 */
class PageViews_Plugin implements Typecho_Plugin_Interface
{
    protected static $pluginName = "PageViews";

    /* 激活插件方法 */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('PageViews_Plugin', 'statisticalPageViews');
        return '插件安装成功，请进入设置填写起始浏览量';
    }

    /* 禁用插件方法 */
    public static function deactivate()
    {
        return '插件卸载成功';
    }

    /* 插件配置方法 */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $views = new Typecho_Widget_Helper_Form_Element_Text("views", null, '873840', '浏览量：', '文章起始浏览量，默认为0');
        $form->addInput($views);
        // TODO: Implement config() method.
    }

    /* 个人用户的配置方法 */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        // TODO: Implement personalConfig() method.
    }

    /**
     * 统计浏览量
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     * @author Pony
     */
    public static function statisticalPageViews()
    {
        if (Typecho_Widget::widget('Widget_Archive')->is('single')) {
            try {
                $pluginConfig = Helper::options()->plugin(self::$pluginName);
                $views = (int) $pluginConfig->views;
                $views++;
                //更新浏览量
                Helper::configPlugin(self::$pluginName, array('views' => $views));
            } catch (\Throwable $e) {
                // 插件文件已放进目录、但还没在后台启用时配置尚未写入。
                // 此时静默跳过，不要把一个统计插件变成全站 500。
            }
        }
    }

    /**
     * 显示浏览量
     * 语法: PageViews_Plugin::showPageViews();
     * 输出：'本站总访问量 XX 次'
     * 语法: PageViews_Plugin::showPageViews('点击量','次');
     * 输出：'点击量 XX 次'
     *
     * @param string $before
     * @param string $after
     * @throws Typecho_Db_Exception
     * @author Pony
     */
    public static function showPageViews($before = '总访问量：', $after = '次')
    {
        $views = 0;
        try {
            $pluginConfig = Helper::options()->plugin(self::$pluginName);
            $views = (int) $pluginConfig->views + 1;
        } catch (\Throwable $e) {
            // 主题常以 class_exists() 判断插件是否存在；只要文件在目录里就会
            // 命中，即使插件未启用。这里降级为 0，避免抛异常拖垮侧栏。
        }
        echo htmlspecialchars($before . $views . $after, ENT_QUOTES, 'UTF-8');
    }
}
