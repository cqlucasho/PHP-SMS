<?php
/**
 * 处理短信错误信息
 */
abstract class SmsErrorException {
    protected static function printf($string) {
        self::$errorInfo = $string;
    }

    protected static $errorInfo = '';
}

/**
 * 短信抽象类, 所有短信类继承此类.
 *
 * @abstract ASms
 * @author lucasho
 */
abstract class ASms extends SmsErrorException {
    /**
     * 初始化
     */
    public function __construct($currTemplate, $templates = null) {
        $this->_current_template = !empty($currTemplate) ? $currTemplate : $this->_current_template;
        $this->_configs = isset($templates) ? $templates : $this->_loadSmsConfig();
    }

    /**
     * 发送短信, 子类重写此方法
     * @return array
     */
    public function sendSms() {
        return array();
    }

    /**
     * 获取生存时间
     */
    public function fetchExpire() {
        return !empty($this->_configs['sms_expire_time']) ? $this->_configs['sms_expire_time'] : $this->_expire;
    }

    /**
     * 获取最后生成的短信信息
     *
     * @return string
     */
    public function fetchTemplateString() {
        return !empty($this->_template_string) ? $this->_template_string : null;
    }

    /**
     * 获取错误信息
     *
     * @return string
     */
    public function fetchErrorInfo() {
        return !empty(self::$errorInfo) ? self::$errorInfo : null;
    }

    /**
     * 初始化缓存对象
     *
     * @param string $host 连接地址
     * @param int $port 端口
     * @param int $timeout 超时时间
     */
    protected function __initialCache($host, $port, $timeout = 3600) {
        $this->_cache = null;
    }

    /**
     * 加载短信模板
     *
     * @return array
     */
    protected function _loadSmsConfig() {
        return require(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'config.php');
    }

    /**
     * 子类可重写此方法
     * @return bool
     */
    protected function _sendSms($mobile, $templateString) {
        # 判断是否开启短信
        if(!$this->_configs['open']) return false;

        if($mobile) {
            # 检测手机网络类型
            $this->_checkNetworkType($mobile);

            # 发送参数到短信平台
            /*$client = new SoapClient(self::SMS_GATEWAY);
            $param = array(
                "userCode"  => $this->_templates['sms_account'],
                "userPass"  => $this->_templates['sms_pass'],
                "DesNo"     => $mobile,
                "Msg"       => $templateString.$this->_templates['sms_sign'],
                "Channel"   => "1"
            );
            $result = $client->__soapCall('SendMsg', array('parameters' => $param));

            # 检查状态
            $this->_checkStatus($result, array('mobile' => $mobile));*/
            if(empty(self::$errorInfo)) {
                $this->_template_string = $templateString;
                return true;
            }
        }

        return false;
    }

    /**
     * 选择模板, 子类可重写此方法.
     *
     * @param $params
     * @param string $validateCode
     * @return string
     */
    protected function _selectTemplate($params, $validateCode = '') {
        return '';
    }

    /**
     * 检测手机通信网络类型
     * @param $mobile
     * @return bool
     */
    protected function _checkNetworkType($mobile) {
        if(!preg_match("/^1[34578][0-9]{9}$/", $mobile)){
            return false;
        }

        if(preg_match("/^((13[4-9])|(147)|(15[0-2,7-9])|(18[2-3,7-8]))\\d{8}$/", $mobile)){
            $this->network_type = '移动';
        }

        if(preg_match("/^((13[0-2])|(145)|(15[5-6])|(18[5-6]))\\d{8}$/", $mobile)){
            $this->network_type = '联通';
        }

        if(preg_match("/^((133)|(153)|(18[0,9]))\\d{8}$/", $mobile)){
            $this->network_type = '电信';
        }
        else {
            $this->network_type = '未知';
        }
    }

    /**
     * 生成随机验证码
     * @return array
     */
    protected function _generateValidateCode($count = 1) {
        return array('code' => mt_rand(100000, 999999), 'count' => $count);
    }

    /**
     * 生成短信存储hash key
     * @param $name
     * @return string
     */
    protected function _generateSmsKey($name) {
        return sha1($name);
    }

    /**
     * 获取验证码
     * @return mixed
     */
    abstract public function getValue($name);

    /**
     * 清除值
     * @param $name
     * @return mixed
     */
    abstract public function cleanValue($name);

    /**
     * 比较值
     * @param $key
     * @param $value
     * @return mixed
     */
    abstract public function compareValue($key, $value);

    /**
     * 设置验证码
     * @return mixed
     */
    abstract protected function _setValue($name, $value, $expire);

    /**
     * 检查短信状态
     *
     * @param $result
     * @param array $params
     */
    private function _checkStatus($result, $params = array()) {
        switch($result->SendMsgResult) {
            case -1 :
                SmsErrorException::printf('提交接口错误');
                break;
            case -3 :
                SmsErrorException::printf("用户名或密码错误");
                break;
            case -4 :
                SmsErrorException::printf("短信内容和备案的模板不一样");
                break;
            case -5 :
                SmsErrorException::printf("签名不正确");
                break;
            case -7 :
                SmsErrorException::printf("余额不足");
                break;
            case -8 :
                SmsErrorException::printf("通道错误");
                break;
            case -9 :
                SmsErrorException::printf("{$this->_network_type}号码{$params['mobile']}：无效号码\n");
                break;
            default :
                break;
        }
    }


    # 配置信息
    protected $_configs = array();

    # 短信验证码标识符
    const SMS_KEY = 'smsAuth';
    # 短信平台地址
    const SMS_GATEWAY = "http://sms.cqmono.cn/api/MsgSend.asmx?WSDL";
    # 短信生存时间
    protected $_expire = 180;
    # 每个手机号最大发送次数
    protected $_max_send = 5;
    # 手机通信网络类型
    protected $_network_type;

    # 当前使用的短信模板名称
    protected $_current_template = 'tpl_sms_authcode';
    # 生成的短信信息
    protected $_template_string = '';

    # 缓存引擎对象
    protected $_cache = null;
}
