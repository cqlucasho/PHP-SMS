<?php
require_once('library'.DIRECTORY_SEPARATOR.'a_sms.php');

/**
 * 短信类, 用于获取短信通知信息.
 *
 * @example
 *  发送:
 *      require_once('extends/tools/sms/sms_memcache.php');
 *      $sms = new SmsMemcache('需要使用的短信模板名称', '传入sms_config文件变量,缺省为自动加载');
 *      $sms->sendSms(array('token' => 'xxx', 'mobile' => 'xxx', 'money' => 'xxx'));
 *
 *  读取:
 *      require_once('extends/tools/sms/sms_memcache.php');
 *      $sms = new SmsMemcache();
 *      $sms->getValue($name);
 */
class SmsMemcache extends ASms {
    public function __construct($currTemplate = null, $templates = null, $cache = true) {
        parent::__construct($currTemplate, $templates);
        $this->__initialCache($this->_configs['cache_address'], $this->_configs['cache_port']);
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
        if(empty($params['token'])) $params['token'] = $params['mobile'];
        $hashKey = $this->_generateSmsKey(self::SMS_KEY.$params['token']);

        # 判断是否已经发送, 并且发送次数未超过限定最大数
        $smsCode = $this->_cache->read($hashKey);
        if(!empty($smsCode)) {
            if($smsCode['count'] < $this->_configs['sms_send_num']) {
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
            $validateCode = $this->_generateValidateCode($smsCode['count']);
            # 生成短信模板
            $templateString = $this->_selectTemplate($params, $validateCode['code']);

            # 执行发送短信
            $success = $this->_sendSms($mobile, $templateString);
            if($success) {
                # 将验证码保存到缓存
                $this->_setValue($hashKey, $validateCode, $this->fetchExpire());
                return true;
            }
        }

        return false;
    }

    /**
     * 只发送短信, 不保存值到缓存.
     *
     * @param array $params 参数列表
     * @return bool
     */
    public function sendSmsMessage($params) {
        if(!empty($params['mobile'])) {
            # 生成短信模板
            $templateString = $this->_selectTemplate($params);

            # 执行发送短信
            return $this->_sendSms($params['mobile'], $templateString);
        }
    }

    /**
     * 选择模板
     *
     * @param $params
     * @param string $validateCode
     * @return string
     */
    protected function _selectTemplate($params, $validateCode = '') {
        $templateString = '';

        switch($this->_current_template) {
            case 'tpl_register_ok':
                $templateString = sprintf($this->_configs[$this->_current_template], $params['username'], $params['password']);
                break;
            default:
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
        if(!empty($templateName)) $this->_current_template = $templateName;
    }

    /**
     * @see ASms::_setValue()
     */
    protected function _setValue($name, $value, $expire) {
        $this->_cache->write($name, $value, $expire);
    }

    /**
     * 从缓存中获取短信.
     * 如果为空, 则表示超时.
     *
     * @param $name
     * @return bool
     */
    public function getValue($name) {
        return ($value = $this->_cache->read($this->_generateSmsKey(self::SMS_KEY.$name))) ? $value['code'] : false;
    }

    /**
     * 清除缓存值
     */
    public function cleanValue($name) {
        return $this->_cache->delete($this->_generateSmsKey(self::SMS_KEY.$name));
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

    /**
     * @see ASms::_send()
     */
    protected function _send() {
        # 发送参数到短信平台
        /*$client = new SoapClient(self::SMS_GATEWAY);
        $param = array(
            "userCode"  => $this->_templates['sms_account'],
            "userPass"  => $this->_templates['sms_pass'],
            "DesNo"     => $mobile,
            "Msg"       => $templateString.$this->_templates['sms_sign'],
            "Channel"   => "1"
        );
        $result = $client->__soapCall('SendMsg', array('parameters' => $param));*/
    }

    /**
     * @see ASms::_checkStatus()
     */
    protected function _checkStatus($result, $params = array()) {
        switch($result) {
            case -1 :
                SmsErrorException::printf('系统异常');
                break;
            default :
                break;
        }
    }
}
