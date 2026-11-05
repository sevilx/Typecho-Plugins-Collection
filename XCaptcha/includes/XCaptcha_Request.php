<?php

/**
 * XCaptcha请求相关
 * @author CairBin(Xinyi Liu)
 */
class XCaptcha_Request
{
    /**
     * curl请求并返回响应，用于服务端向验证服务器的校验
	 * @static 
     * @param  string $urlPath   请求路径
     * @param  string $secretKey 密钥
     * @param  string $postToken token
     * @return object 响应结果
     */    
    public static function makeCaptchaRequest($urlPath, $secretKey, $postToken)
    {
        $postData = http_build_query([
			'secret' => $secretKey,
			'response' => $postToken
		]);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $urlPath);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 secs timeout
		
		$response = curl_exec($ch);
		
		if (curl_errno($ch)) {
			echo '请求错误: ' . curl_error($ch);
		}
		
		curl_close($ch);
        return json_decode($response);
    }
}