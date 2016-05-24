<?php
require_once('library'.DIRECTORY_SEPARATOR.'a_sms.php');

/**
 * 短信类, 用于获取短信通知信息.
 *
 * @example
 *  发送:
 *      require_once('extends/tools/sms/sms_memcache.php');
 *      $sms = new SmsMemcache('需要使用的短信模板名称', $db_object, '传入sms_config文件变量,缺省为自动加载');
 *      $sms->sendSms(array('token' => 'xxx', 'mobile' => 'xxx', 'money' => 'xxx'));
 *
 *  读取:
 *      require_once('extends/tools/sms/sms_memcache.php');
 *      $sms = new SmsMemcache();
 *      $sms->getValue($name);
 */
class SmsMemcache extends ASms {
    public function __construct($currTemplate = null, $db = null, $templates = null, $cache = true) {
        parent::__construct($currTemplate, $db, $templates);

        #　初始化缓存
        if($cache) {
            $this->__initialCache('127.0.0.1', 12000);
        }
    }

    /**
     * 发送短信, 用于验证码保存到缓存服务器.
     * 如果需要新的需求, 建议新建类文件并继承ASms类.
     *
     * $params包含成员变量:
     *      'token': 唯一标识符. 用于生成缓存key, 如果未指定token值, 则使用手机号作为缓存key保存.
     *      'mobile': 手机号
     *      [*]: 其它参数
     *
     * @param array $params
     * @return bool
     */
    public function sendSms($params = array()) {
        # 生成hash
        if(!isset($params['token'])) $params['token'] = $params['mobile'];
        $hashKey = $this->generateSmsKey(self::SMS_KEY.$params['token']);

        # 判断是否已经发送, 并且发送次数未超过限定最大数
        $smsCode = $this->cache->read($hashKey);
        if(!empty($smsCode)) {
            if($smsCode['count'] < $this->_max_send) {
                $smsCode['count'] = $smsCode['count']+1;
                $this->cleanValue($params['token']);
            }
            else {
                return false;
            }
        }

        if(!empty($params)) {
            $mobile = !empty($params['mobile']) ? $params['mobile'] : '';

            # 生成验证码
            $validateCode = $this->generateValidateCode($smsCode['count']);
            # 生成短信模板
            $templateString = $this->selectTemplate($params, $validateCode['code']);

            # 执行发送短信
            $success = $this->_sendSms($mobile, $templateString);
            if($success) {
                # 将验证码保存到缓存
                $this->setValue($hashKey, $validateCode, $this->fetchExpire());
                return true;
            }
        }

        return false;
    }

    /**
     * 只发送短信, 不保存值到缓存.
     *
     * @param array $params 参数列表
     */
    public function sendSmsMessage($params) {
        if(!empty($params['mobile'])) {
            # 生成短信模板
            $templateString = $this->selectTemplate($params);

            # 执行发送短信
            $this->_sendSms($params['mobile'], $templateString);
        }
    }


    /**
     * 选择模板
     *
     * @param mixed $params
     * @param string $validateCode
     * @return string
     */
    public function selectTemplate($params, $validateCode = '') {
        switch($this->current_template) {
            case 'tpl_register_ok':
                $templateString = sprintf($this->templates[$this->current_template], $params['username'], $params['password']);
                break;
            default:
                $templateString = sprintf($this->templates[$this->current_template], $validateCode);
                break;
        }

        return $templateString;
    }

    /**
     * 设置当前模板名称
     *
     * @param string $templateName 模板名
     */
    public function setCurrentTemplate($templateName = '') {
        if(!empty($templateName)) $this->current_template = $templateName;
    }

    /**
     * 保存短信到缓存
     *
     * @param string $name     缓存key
     * @param mixed $value    缓存值
     * @param int $expire 有效期
     */
    protected function setValue($name, $value, $expire) {
        $this->cache->write($name, $value, $expire);
    }

    /**
     * 从缓存中获取短信.
     * 如果为空, 则表示超时.
     *
     * @param $name
     * @return bool
     */
    public function getValue($name) {
        return ($value = $this->cache->read($this->generateSmsKey(self::SMS_KEY.$name))) ? $value['code'] : false;
    }

    /**
     * 清除缓存值
     */
    public function cleanValue($name) {
        return $this->cache->delete($this->generateSmsKey(self::SMS_KEY.$name));
    }

    /**
     * 比较值
     *
     * @param string $key  获取指定缓存的key
     * @param mixed $value 需要比较的值
     * @return bool
     */
    public function compareValue($key, $value = '') {
        if(($getValue = $this->getValue($key)) && !empty($value)) {
            return ($getValue == $value) ? true : false;
        }

        return false;
    }
}
