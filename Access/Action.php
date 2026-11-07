<?php
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

require_once __DIR__ . '/Access_Bootstrap.php';

class Access_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $access;

    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);

        $this->access = new Access_Core();
    }

    public function execute()
    {}

    public function action()
    {}

    public function writeLogs()
    {
        $image = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAQUAP8ALAAAAAABAAEAAAICRAEAOw==');
        $this->response->setContentType('image/gif');
        if ($this->access->config->writeType == 1) {
            $this->access->writeLogs(null, $this->request->u, $this->request->cid, $this->request->mid);
        }
        echo $image;
    }

    protected function checkAuth()
    {
        if (!$this->access->isAdmin()) {
            throw new Exception('Access Denied', 401);
        }
    }

    /**
     * 后台写操作的 CSRF 令牌：基于 Typecho 站点密钥，攻击者无法从跨域读取。
     * 与主题/其他插件使用的 Widget_Security 同一套密钥体系。
     */
    public static function csrfToken()
    {
        if (class_exists('\Widget\Security')) {
            return \Widget\Security::alloc()->getToken('access-csrf');
        }
        if (class_exists('Widget_Security')) {
            return Typecho_Widget::widget('Widget_Security')->getToken('access-csrf');
        }
        // fail-closed：拿不到安全组件时拒绝写操作，而不是静默放行。
        throw new Exception('Security component unavailable', 500);
    }

    /**
     * 所有会改动数据的 rpc 必须校验令牌，防止已登录管理员被跨域诱导发帖。
     */
    protected function protectWrite($rpcType)
    {
        if (!in_array((string) $rpcType, array('delete', 'migrate'), true)) {
            return;
        }
        $expected = (string) self::csrfToken();
        $given = (string) $this->request->get('_', '');
        if ($given === '' || !hash_equals($expected, $given)) {
            throw new Exception('Invalid CSRF token', 403);
        }
    }

    public function migration() {
        try {
            $this->checkAuth(); # 鉴权
            $rpcType = $this->request->get('rpc'); # 业务类型
            $this->protectWrite($rpcType);
            $logs = new Access_Migration($this->request);
            $data = $logs->invoke($rpcType); # 进行业务分发并调取数据
            $errCode = 0;
            $errMsg = 'ok';
        } catch (Exception $e) {
            $data = null;
            $errCode = $e->getCode();
            $errMsg = $e->getMessage();
        }

        $this->response->throwJson([
            'code' => $errCode,
            'message' => $errMsg,
            'data' => $data
        ]);
    }

    public function logs() {
        try {
            $this->checkAuth(); # 鉴权
            $rpcType = $this->request->get('rpc'); # 业务类型
            $this->protectWrite($rpcType);
            $logs = new Access_Logs($this->request);
            $data = $logs->invoke($rpcType); # 进行业务分发并调取数据
            $errCode = 0;
            $errMsg = 'ok';
        } catch (Exception $e) {
            $data = null;
            $errCode = $e->getCode();
            $errMsg = $e->getMessage();
        }

        $this->response->throwJson([
            'code' => $errCode,
            'message' => $errMsg,
            'data' => $data
        ]);
    }

    public function statistic() {
        try {
            $this->checkAuth(); # 鉴权
            if(!$this->request->isGet())
                throw new Exception('Method Not Allowed', 405);

            $rpcType = $this->request->get('rpc'); # 业务类型
            $statistic = new Access_Statistic($this->request);
            $data = $statistic->invoke($rpcType); # 进行业务分发并调取数据
            $errCode = 0;
            $errMsg = 'ok';
        } catch (Exception $e) {
            $data = null;
            $errCode = $e->getCode();
            $errMsg = $e->getMessage();
        }

        $this->response->throwJson([
            'code' => $errCode,
            'message' => $errMsg,
            'data' => $data
        ]);
    }
}
