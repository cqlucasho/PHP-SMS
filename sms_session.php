<?php
require_once('library'.DIRECTORY_SEPARATOR.'a_sms.php');

/**
 * 短信类(session版), 用于获取短信通知信息.
 *
 * @example
 *  发送:
 *      require_once('sms_session.php');
 *      $sms = new SmsSession('需要使用的短信模板名称', '传入sms_config文件变量,缺省为自动加载');
 *      $sms->sendSms(array('token' => 'xxx', 'mobile' => 'xxx', 'money' => 'xxx'));
 *
 *  读取:
 *      require_once('sms_session.php');
 *      $sms = new SmsSession();
 *      $sms->getValue($name);
 */
class SmsSession extends ASms {
    public function __construct($currTemplate = null, $templates = null) {
        parent::__construct($currTemplate, $templates);
    }

    /**
     * 发送短信, 用于验证码保存到session存储.
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
        # 生成hash key
        if(empty($params['token'])) $params['token'] = $params['mobile'];
        $hashKey = $this->_generateSmsKey(self::SMS_KEY.$params['token']);
        
        # 判断是否已经发送并且判断时间是否过期
        if($_SESSION[$hashKey.'life'] > time()) return false;
        if(isset($_SESSION[$hashKey]) && !empty($_SESSION[$hashKey])) $this->cleanValue($params['token']);

        if(!empty($params)) {
            $mobile = !empty($params['mobile']) ? $params['mobile'] : '';

            # 生成验证码
            $validateCode = $this->_generateValidateCode();
            # 生成短信模板
            $templateString = $this->_selectTemplate($params, $validateCode['code']);

            # 执行发送短信
            $success = $this->_sendSms($mobile, $templateString);
            if($success) {
                # 将验证码保存到缓存
                $this->_setValue($hashKey, $validateCode);
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
    protected function _setValue($key, $value, $expire = 0) {
        $_SESSION[$key] = $value;
    }

    /**
     * 从缓存中获取短信.
     * 如果为空, 则表示超时.
     *
     * @param string|int $key 键名
     * @return bool
     */
    public function getValue($key) {
        $genKey = $this->_generateSmsKey(self::SMS_KEY.$key);

        return (isset($_SESSION[$genKey]) && ($_SESSION[$genKey.'life'] > time()) && ($value = $_SESSION[$genKey])) ? $value['code'] : false;
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
            if($getValue == $value) {
                $this->cleanValue($key);
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * 清除缓存值
     *
     * @param string $key 键名
     */
    public function cleanValue($key) {
        $genKey = $this->_generateSmsKey(self::SMS_KEY.$key);
        $_SESSION[$genKey] = '';
        $_SESSION[$genKey.'life'] = '';
        unset($_SESSION[$genKey]);
        unset($_SESSION[$genKey.'life']);
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
     * @see ASms::_send()
     */
    protected function _send($mobile, $template) {
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
            case -117 :
                SmsErrorException::printf('发送短信失败');
                break;
            case 305 :
                SmsErrorException::printf('提交接口错误');
                break;
            case 101:
            case 303:
                SmsErrorException::printf('客户端网络故障');
                break;
            case 307:
                SmsErrorException::printf("{$this->_network_type}号码{$params['mobile']}：无效号码\n");
                break;
            default :
                break;
        }
    }
}
