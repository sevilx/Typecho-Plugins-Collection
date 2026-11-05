<?php
namespace TypechoPlugin\FuckAdComment;

use \Typecho\Db;
use \Typecho\Widget;
use Utils\Helper;
use Widget\Notice;
use Widget\User;
use Widget\Options;
use Widget\Security;

// 模型
class Model extends Widget implements \Widget\ActionInterface
{

    private $user; // 当前登录用户信息
    private $security; // 安全组件
    private $db; // 数据库组件
    private $options;
    private $config;

    // 插件名称
    protected static $pluginName = 'FuckAdComment';
    protected static $summaryKey = 'FuckAdComment:summary';

    // 评论可操作属性
    const ACTION_MAP = [
        'author' => '昵称',
        'ip' => 'IP地址',
        'text' => '内容',
        'url' => '网址',
        'mail' => '邮箱'
    ];

    public function __construct(Widget\Request $request, Widget\Response $response, $params = null)
    {
        parent::__construct($request, $response, $params);
        $this->db = Db::get();
        $this->user = User::alloc();
        $this->security = Security::alloc();
        $this->options = Options::alloc();
    }

    // action 行为入口函数
    public function action()
    {
        // 只允许贡献者以上的用户访问（不包含贡献者）
        $this->user->pass('editor');

        // url token 安全
        $this->security->protect();

        $this->config = $this->options->plugin(self::$pluginName);
        $this->on($this->request->is('do'))->DoBlack();
    }

    /**
     * 拉黑数据
     * @return void
     */
    public function DoBlack()
    {
        // 获取行为标识
        $action = $this->request->get('do');

        // 校验行为标识是否合法
        if (!isset(self::ACTION_MAP[$action])) {
            Notice::alloc()->set('操作评论属性不存在', 'error');
            $this->response->goBack();
        }

        // 获取要拉黑的内容和已有配置内容
        $content = trim($this->request->get('content'));
        $oldText = $this->config->$action ?? '';

        // 检查新内容是否已存在
        if (in_array($content, array_map('trim', explode(PHP_EOL, $oldText)), true)) {
            Notice::alloc()->set('操作的屏蔽词已存在', 'error');
            $this->response->goBack();
        }

        // 拼接新内容
        $newText = $oldText ? rtrim($oldText, PHP_EOL) . PHP_EOL . $content : $content;

        // 更新插件配置
        Helper::configPlugin(self::$pluginName, [$action => $newText]);

        $this->nextAction($action,$content);

        // 设置成功提示并返回
        Notice::alloc()->set('评论过滤关键词操作成功');
        $this->response->goBack();
    }

    // 根据配置进行下一步处理
    private function nextAction($action,$content)
    {
        // 选项 0 直接结束 不进行任何处理
        if (!$this->config->black_next) return;

        // 判断是否需要同步处理其他待处理评论
        if (in_array('relevance',$this->config->ext)) {
            // 构建查询
            $whereStr = sprintf('status = \'waiting\' AND %s LIKE ?', $action);
            $query = $this->db->select('coid')->from('table.comments')->where($whereStr,'%'.$content.'%');
            $list = $this->db->fetchAll($query);
            $cids = array_column($list,'coid');
        } else {
            // 不处理其他评论，就只操作当前评论
            $cids[] = $this->request->get('cid');
        }
        if ($this->config->black_next === '1') {
            // 标为垃圾
            $query = $this->db->update('table.comments')->rows(['status' => 'spam'])->where('coid in ?', $cids);
        } else {
            $query = $this->db->delete('table.comments')->where('coid in ?', $cids);
        }

        return $this->db->query($query);
    }

    /** 自动更新违规词库
     * @param $config
     * @param $comment
     * @return int
     */
    public static function autoUpdate($config,$comment) {
        $num = 0;
        $update = [];
        // 遍历现有的数据
        foreach (self::ACTION_MAP as $key => $val) {
            // 如果在当前词库中没有找到当前提交的字段 就收集
            $oldText = $config->$key ?? '';
            $addText = trim($comment[$key]);

            // 如果当前字段为空 就不处理
            if (!$addText) continue;
            $isExists = false;
            $rules = array_filter(array_map('trim', explode(PHP_EOL, $oldText)));
            foreach ($rules as $rule) {
                // 如果规则匹配评论内容
                if (!empty($rule) && strpos($addText, $rule) !== false) {
                    $isExists = true;
                    break; // 提前结束循环，提升效率
                }
            }
            // 都没匹配上 就收集上
            if (!$isExists) {
                $update[$key] = $oldText ? rtrim($oldText, PHP_EOL) . PHP_EOL . $addText : $addText;
                $num++;
            }
        }

        Helper::configPlugin(self::$pluginName, $update);

        return $num;
    }
}