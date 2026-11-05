<?php
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }


/**
 * 极验验证插件执行
 */
class XCaptcha_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function execute()
    {
    }

    public function action()
    {
        $this->on($this->request->is('do=ajaxResponseCaptchaData'))->ajaxResponseCaptchaData();
    }

    public function ajaxResponseCaptchaData()
    {
        // if (!$this->request->isAjax()) {
        //     $this->response->redirect('/');
        // }
        $request = Typecho_Request::getInstance();
        $type = $request->get('type', 'altcha'); // 默认为 'altcha'
        // 根据不同的参数调用不同的函数
        switch ($type) {
            case 'geetest':
                Typecho_Plugin::factory('XCaptcha')->responseGeetest();
                break;
            case 'altcha':
                Typecho_Plugin::factory('XCaptcha')->responseAltcha();
                break;
            default:
                echo json_encode(['error' => 'Invalid captcha type']);
                break;
        }

    }
}