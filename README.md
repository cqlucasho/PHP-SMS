#通用短信发送类

实例查看：sms_memcache.php
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
