#通用短信发送类

sms_memcache.php(使用缓存)
sms_session.php(使用session)
以上两个类的使用方式一样，使用前请根据需求修改两个类.

protected function _send(): 此方法请实现短信商的接口调用
protected function _selectTemplate($params, $validateCode = ''): 请根据需求判断是否需要
protected function _checkStatus(): 请根据短信商返回的状态自定义修改

使用方法：
    
    /**
     * 发送单一模板短信
     */
    # 初始化短信类
    $smsObj = new SmsMemcache($currTemplate);
    # 发送短信
    if($smsObj->sendSms($this->fetchPostParams())) {
        die(json_encode($this->statusCode($smsObj->fetchExpire())));
    }

    /**
     * 多次模板切换发送
     */
    # 初始化短信类
    $smsObj = new SmsMemcache(null);
    foreach($params['currTemplate'] as $templates) {
        $smsObj->setCurrentTemplate($templates['name']);
        $smsObj->sendSmsMessage($templates);
    }
