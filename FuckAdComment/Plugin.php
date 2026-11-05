<?php
namespace TypechoPlugin\FuckAdComment;

use Typecho\Plugin\Exception;
use Typecho\Common;
use Typecho\Plugin\PluginInterface;
use Typecho\Request;
use Typecho\Widget;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Layout;
use Utils\Helper;
use Widget\Feedback;
use Widget\Options;
use Widget\Plugins\Edit;
use Widget\User;


if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 垃圾评论过滤插件 <a href="https://www.dbkuaizi.com/archives/395.html">使用帮助</a>
 *
 * @package FuckAdComment
 * @author 两双筷子
 * @version 1.0.0
 * @link https://www.dbkuaizi.com
 */

class Plugin implements PluginInterface
{
    // 插件名称
    protected static $pluginName = 'FuckAdComment';
    protected static $summaryKey = 'FuckAdComment:summary';
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 校验版本是否为 1.2.0+
        if (version_compare(Common::VERSION, '1.2.0', '<')) {
            throw new Exception('此插件仅支持 1.2.0 及以上版本的 Typecho 程序');
        }
        // 注册统计
        Edit::configPlugin(self::$summaryKey,['total'=> 0,'auto' => 0,'filter' => 0]);

        // 注册拉黑评论
        Helper::addAction('FuckAdComment',Model::class);
        \Typecho\Plugin::factory('admin/footer.php')->begin = array(__CLASS__, 'render');
        \Typecho\Plugin::factory(Feedback::class)->comment = array(__CLASS__, 'filter');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){
        $pluginConfig = Options::alloc()->plugin(self::$pluginName);
        if (!$pluginConfig->uninstall) {
            throw new Exception('为了防止误操作，请先设置 “允许卸载插件” 后再试。');
            return;
        }
        Edit::configPlugin(self::$summaryKey,[]);
        // 卸载注册的行为
        Helper::removeAction('FuckAdComment');
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param $form 配置面板
     * @return void
     */
    public static function config(Form $form)
    {
        $summary = ['total'=> 0,'auto' => 0,'filter' => 0];
        // 判断插件是否启动 只有启动了才从配置中获取
        if (Options::alloc()->plugins['activated'][self::$pluginName]) {
            $configData = Options::alloc()->plugin(self::$pluginName);
            $summary = Options::alloc()->plugin(self::$summaryKey);
            // 统计总条数
            foreach (Model::ACTION_MAP as $k => $v) {
                if (!empty($configData->$k)) {
                    $summary['total'] += (preg_match_all('/\R/', $configData->$k ?? '') + 1);
                }
            }
        }

        $html = new Layout('p',['class' => 'message notice']);
        $html->html(sprintf('屏蔽词库: <b>%s</b> 条，拦截：<b>%s</b> 次，自动收集：<b>%s</b> 条',
            $summary['total'],$summary['filter'],$summary['auto']));
        $form->addItem($html);

        foreach (Model::ACTION_MAP as $key => $value){
            $input = new Form\Element\Textarea($key,NULL,'',
                sprintf('%s屏蔽词',$value),
            sprintf('一行一条屏蔽词，提交评论的 <b>%s</b> 包含过滤词则会被拦截',$value));
            $form->addInput($input);
        }
        // 拉黑后关联操作
        $blackNextOption = [0 => '无动作',1 => '标为垃圾', 2=>'删除评论'];
        $blackNextRadio = new Form\Element\Radio('black_next',$blackNextOption,0,_t('拉黑后续操作'));
        $form->addInput($blackNextRadio);

        $extension = [
            'autoExt' => '当评论被拦截时，自动收集该评论的其他属性到拦截词库中',
            'relevance' => '在评论管理页面拉黑操作时，同时处理其他符合条件的待审核评论'
        ];

        // 拦截时提示
        $noticeList = [
            '0' => '提示型: <b>该评论被禁止提交</b>',
            '1' => '详细提示: <b>评论 “邮件” 中不能包含 "@qq.com"</b>',
            '2' => '伪装型: <b>500 Server Error</b>',
            '3' => '自定义: <input type="text" class="w-50 text-s mono" name="custom" value="'.htmlspecialchars($configData->custom??'').'" />'
        ];

        // 添加 custom 隐藏输入框，解决渲染页面 没有 custom 配置报错的问题
        $customHidden = new Form\Element\Hidden('custom',null,'');
        $form->addInput($customHidden);

        $noticeInput = new Form\Element\Radio('notice',$noticeList,'0','拦截提示规则');
        $form->addInput($noticeInput->multiMode());
        // 自动扩展
        $extensionInput = new Form\Element\Checkbox('ext',$extension,['autoExt'],'扩展选项');
        $form->addInput($extensionInput->multiMode());

        // 防呆设计
        $uninstall = new Form\Element\Radio('uninstall',
            array(1 => _t('允许卸载'), 0 => _t('禁止卸载')), '0', _t('是否允许卸载插件'), _t('卸载插件会导致所有的屏蔽清空，且无法恢复，请慎重选择。'));
        $form->addInput($uninstall);
    }
    
    // 个人用户的配置面板
    public static function personalConfig(Form $form){}

    /**
     * 插件实现方法
     * @access public
     * @return void
     */
    public static function render()
    {
        // 如果当前不是评论页面就退出
        if (strpos(Request::getInstance()->getRequestUri(),'manage-comments.php') === false) return;

        // 获取 安全 token 请求时用
        $params = [
            '{securityUrl}' => Helper::security()->getIndex('/action/FuckAdComment'),
            '{actionMap}' => json_encode(Model::ACTION_MAP),
            '{pluginConfig}' => '',
        ];

        // 判断是否是管理员，管理员可访问插件配置链接
        if (User::alloc()->pass('administrator', true)) {
            $params['{pluginConfig}'] = Options::alloc()->adminUrl('options-plugin.php?config=' . self::$pluginName,true);
        }

        // 评论页拉黑操作按钮的模板，放在 Plugin 里面太乱了
        $template = file_get_contents(__DIR__.'/action.tpl');

        // 替换模板变量，输出渲染后的内容
        echo str_replace(array_keys($params),array_values($params),$template);
    }

    /**
     * 评论过滤器
     * @param $comment
     * @param $post
     * @return void
     */
    public static function filter($comment,$post) {
        // 评论状态 是否拦截
        $state = false;
        $param = [];
        $config = Options::alloc()->plugin(self::$pluginName);

        // 遍历动作映射并执行规则检查
        foreach (Model::ACTION_MAP as $key => $val) {
            // 获取规则并按行分隔
            $rules = array_filter(array_map('trim', explode(PHP_EOL, $config->$key ?? '')));

            foreach ($rules as $rule) {
                // 如果规则匹配评论内容，设置拦截状态
                if (!empty($rule) && strpos($comment[$key] ?? '', $rule) !== false) {
                    $param = ['label' => $val,'content' => $rule];
                    $state = true;
                    break 2; // 提前结束循环，提升效率
                }
            }
        }

        // 评论没问题 直接结束判断
        if (!$state) return $comment;

        // 处理拦截逻辑
        switch ($config->notice) {
            case '0':
                $msg = '该评论被禁止提交';
                break;
            case '1':
                $msg = sprintf('“%s” 中不能包含 “%s”',$param['label'],$param['content']);
                break;
            case '2':
                $msg = '500 Server Error';
                break;
            case '3':
                $msg = $config->custom?? '';
                break;
        }
        $summaryData = Options::alloc()->plugin(self::$summaryKey);
        $newSummary = [
            'auto' => $summaryData['auto'],
            'filter' => $summaryData['filter'],
        ];
        // 判断是否自动收集
        if (in_array('autoExt',$config->ext)) {
            $newSummary['auto'] += Model::autoUpdate($config,$comment);
        }
        // 添加一条拦截记录
        $newSummary['filter']++;
        Helper::configPlugin(self::$summaryKey,$newSummary);

        // 报个错
        throw new Widget\Exception($msg,403);
    }
}