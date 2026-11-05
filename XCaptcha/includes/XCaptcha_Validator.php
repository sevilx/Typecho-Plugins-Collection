<?php

include_once 'XCaptcha_Request.php';
include_once 'XCaptcha_Utils.php';

/**
 * 校验验证码
 * @author CairBin(Xinyi Liu)
 */
class XCaptcha_Validator
{
    /**
     * 极验证二次校验
     * @param Widget_Options::plugin $filter XCaptcha插件对象
     * @return false
     */    
    public static function verifyGeetest(XCaptcha_Config $config)
    {
        if (!isset($_POST['geetest_challenge']) || !isset($_POST['geetest_validate']) || !isset($_POST['geetest_seccode'])) {
            return 0;
        }

        @session_start();
        $geetestSdk = new GeetestLib($config->getCaptchaId(), $config->getSecretKey());
        if (!empty($_SESSION['gt_server_ok'])) {
            $widgetRequest = Typecho_Widget::widget('Widget_Options')->request;
            $agent = $widgetRequest->getAgent();
            $clientType = XCaptcha_Utils::isMobile($agent) ? 'h5' : 'web';
            $ipAddress = $widgetRequest->getIp();

            $data = array(
                'user_id' => $_SESSION['gt_user_id'],
                'client_type' => $clientType,
                'ip_address' => $ipAddress
            );

            return $geetestSdk->success_validate($_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode'], $data);
        }
        return $geetestSdk->fail_validate($_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode']);

    }

    /**
     * 验证Altcha
     * @static
     * @param  XCaptcha_Config $config  配置
     * @return boolean                  验证是否通过
     */    
    public static function verifyAltchaSolution(XCaptcha_Config $config){

        require_once dirname(__DIR__) . "/lib/class.altcha.php";
        // 从 Session 中获取挑战信息和 hmacKey
        @session_start();
        $challenge = $_SESSION['altcha_challenge'] ?? null;
        $signature = $_SESSION['altcha_signature'] ?? null;
        $salt = $_SESSION['altcha_salt'] ?? null;
        $hmacKey = $_SESSION['altcha_hmac_key'] ?? null;
        if (!$challenge || !$signature || !$salt || !$hmacKey) {
            return false; // 挑战信息或 hmacKey 不存在
        }
        $userSolution = $_POST['altcha'] ?? null;
        // 构造验证数据
        $payload = $userSolution;
        // 验证解决方案
        $isValid = \AltchaOrg\Altcha\Altcha::verifySolution($payload, $hmacKey);

        // 清除 Session 中的挑战信息和 hmacKey
        unset($_SESSION['altcha_challenge']);
        unset($_SESSION['altcha_signature']);
        unset($_SESSION['altcha_salt']);
        unset($_SESSION['altcha_hmac_key']);

        return $isValid;
    }


    /**
     * 验证其他Captcha
     * @static
     * @param  XCaptcha_Config $config  配置
     * @return boolean                  验证是否通过
     */    
    public static function verifyOtherCaptcha(XCaptcha_Config $config)
    {
        list($postToken, $urlPath) = XCaptcha_Utils::getCaptchaTokenAndUrl($config->getCaptchaChoosen(), $config->getVerifyUrl());
        $responseData = XCaptcha_Request::makeCaptchaRequest($urlPath, $config->getSecretKey(), $postToken);

        return $responseData->success == true;
    }
}