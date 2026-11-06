<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 该插件会让文章加密功能只加密文章内容！而不影响标题，标签还有评论数！【兼容情况：typecho1.1，开发板】
 * 
 * @package Titleshow
 * @author 泽泽
 * @version 1.1.2
 * @link https://github.com/jrotty/Titleshow
 */
class Titleshow_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->filter = array('Titleshow_Plugin', 'tshow');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
?><style>@media (max-width: 767px){.yaofan {display: none!important;}}</style><?php
      $say=array(
        "看到下面那个大大的二维码了吗，想不想用你大大的手机扫扫它！",
        "好几天没吃早饭了，打赏下开发者吧！",
        "小伙子，插件好用么，打赏下作者好吗？",
        "如果觉得好用，可以扫描下方二维码进行打赏，支持作者！",
        "你知道吗，我特别喜欢听人民币到账的提示音！",
        "听说，打赏我的人最后都找到了真爱。",
        "打赏的都是天使。",
        "打赏了的人都会变美~",
        "打赏3块钱，帮我买杯肥宅快乐水，继续创作，谢谢大家！",
        "阔乐，我想和大阔乐，就差3块钱了！",
                         );
        $tixing = new Typecho_Widget_Helper_Form_Element_Text('tixing', NULL, NULL, _t('密码文字提醒'), _t('不填写则默认为【请输入密码访问】<div class="yaofan"><br>
        <b>作者 ❤ 语：'.$say[rand(0,9)].'</b><br><br><img src="'.Helper::options()->pluginUrl.'/Titleshow/yaofan.jpg" style="max-width: 100%;">
        </div>'));
        $form->addInput($tixing);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 插件实现方法
     * 
     * @access public
     * @return void
     */
public static function tshow($v, $obj) {
    if (!is_array($v)) {
        return $v;
    }

    $tixing = '';
    try {
        $tixing = Typecho_Widget::widget('Widget_Options')->plugin('Titleshow')->tixing; //获取设置参数
    } catch (\Throwable $e) {
        // 插件文件在目录里但尚未启用时配置不存在，用默认提示即可
    }
    if (empty($tixing)) {
        $tixing = '请输入密码访问';
    }
    $v['titleshow'] = false;

    /** 如果访问权限被禁止【就是如果需要密码】
     *  PHP 8 下直接读 $v['hidden'] 会在没有该键的行（侧栏「最新评论」等
     *  复用 filter 钩子的数据）里抛 Warning，警告文本会被直接写进页面，
     *  把 <a href="..."> 冲成坏标签。改用 empty() 并给其余键兜底。 */
    if (!empty($v['hidden'])) {
        $permalink = isset($v['permalink']) ? (string) $v['permalink'] : '';
        $cid = isset($v['cid']) ? (int) $v['cid'] : 0;
        $v['text'] = '
!!!
<form class="protected" action="' . htmlspecialchars(Typecho_Widget::widget('Widget_Security')->getTokenUrl($permalink), ENT_QUOTES, 'UTF-8') . '" method="post">'
            . '<p class="word">' . htmlspecialchars($tixing, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><input type="password" class="text" name="protectPassword" /><input type="hidden" name="protectCID" value="' . $cid . '" />&nbsp;<input type="submit" class="submit" value="' . _t('提交') . '" /></p>'
            . '</form>
!!!
';
        /** 跳过系统默认 */
        $v['hidden'] = false;
        /** 用于模板判断插件 */
        $v['titleshow'] = true;
    }
    /** 返回数据 */
    return $v;
}

}
