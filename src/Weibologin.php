<?php

namespace Weibohit;

use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Client;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;

/**
 * Class Weibologin
 * @property Client $client
 */
class Weibologin
{
    /**
     * @var string|null
     */
    protected $userkey;

    /**
     * @var string|null
     */
    protected $userinfo;

    /**
     * @var Client|null
     */
    protected $client;

    private $loginUrl = 'https://login.sina.com.cn/sso/login.php?client=ssologin.js(v1.4.19)';


    protected $config;

    static $cookieDir = __DIR__ . '/db/';

    public function __get($name)
    {
        return isset($this->$name) ? $this->$name : null;
    }

    public function __construct($username)
    {
        $userkey = self::getUsername($username);
        $this->config = Storage::getInstance()->get('Config', $userkey);

        $cookie = new FileCookieJar(self::$cookieDir . $userkey, true);
        $config = [
            'headers' => [
                'Referer' => 'https://mail.sina.com.cn/?from=mail',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
            ],
            'cookies' => $cookie
        ];

        if (!empty($this->config['proxy'])) {
            $proxy = $this->config['proxy'];
            $config['proxy'] = [
                'http' => 'http://' . $proxy['ip'] . ':' . $proxy['port'],
                'https' => 'https://' . $proxy['ip'] . ':' . $proxy['port']
            ];
        }

        $this->client = new Client($config);
        $this->userkey = $userkey;
    }

    public static function createFolder($folder)
    {
        // Test write-permissions for the folder and create/fix if necessary.
        if ((is_dir($folder) && is_writable($folder))
            || (!is_dir($folder) && mkdir($folder, 0755, true))
            || chmod($folder, 0755)
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function login($password, $energyUrl = '')
    {
        // 邮箱登录->微博登录->带登录信息进入能量榜
        $predata = $this->getPrelogindata($this->userkey);
        $crossDomainUrl = $this->loginFirst($predata, $password);
        $userinfo = $this->loginSecond($crossDomainUrl);
        $this->userinfo = $userinfo['userinfo'];
        if ($energyUrl) {
            $this->loginEnergy($energyUrl);
        }
    }

    public function relogin($doorcode, $energyUrl = '')
    {
        $params = Storage::getInstance()->get('Logindata', Weibologin::getUsername($this->userkey));
        if (!$params) {
            throw new \Exception("登录信息获取失败，请重新登录");
        }
        $params['door'] = $doorcode;

        $response = $this->client->post($this->loginUrl, ['form_params' => $params]);
        $result = $response->getBody()->getContents();
        $result = json_decode($result, true);
        if ($result['retcode'] != 0) {
            throw new \Exception('get redirect url failed, response content: ' . json_encode($result));
        }

        $userinfo = $this->loginSecond($result['crossDomainUrlList'][0]);
        $this->userinfo = $userinfo['userinfo'];
        if ($energyUrl) {
            $this->loginEnergy($energyUrl);
        }
    }

    private function _download($url, $filePath = '')
    {
        //curl
        $ch = curl_init();
        $timeout = 60;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); //要加上，不然报错：Could not resolve host 
        $fp = fopen($filePath, 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        if ($error = curl_error($ch)) {
        }
        curl_close($ch);
    }

    public function loginEnergy($energyUrl)
    {
        $url = 'https://login.sina.com.cn/sso/login.php';
        $params = [
            'url' => $energyUrl,
            '_rand' =>  microtime(true),
            'gateway' => 1,
            'service' => 'weibo',
            'entry' => 'miniblog',
            'useticket' => '0',
            'returntype' => 'META',
            'sudaref' => '',
            '_client_version' => '0.6.23'
        ];
        $url .= '?' . http_build_query($params);
        $response = $this->client->get($url);
        $result = $response->getBody()->getContents();
        $pattern = '/\("(.*?)"\)/';
        preg_match($pattern, $result, $matches);
        if ($matches) {
            $url = $matches[1];
            $response = $this->client->get($url);
            $result = ['code' => $response->getStatusCode(), 'content' => $response->getBody()->getContents()];
        }
    }

    private function loginFirst($predata, $password)
    {
        // 发起第一次登录请求，获取登录请求跳转页redirect_login_url
        $params = [
            'su' => $this->userkey,
            'servertime' => $predata['servertime'],
            'nonce' => $predata['nonce'],
            'sp' => $this->getPassword($password, $predata['servertime'], $predata['nonce'], $predata['pubkey']),
            'rsakv' => $predata['rsakv'],
            'entry' => 'cnmail',
            'gateway' => '1',
            'from' => '',
            'savestate' => '30',
            'qrcode_flag' => 'false',
            'useticket' => '0',
            'ssosimplelogin' => '1',
            'vsnf' => '1',
            'service' => 'sso',
            'pwencode' => 'rsa2',
            'sr' => '1920*1080',
            'encoding' => 'UTF-8',
            'cdult' => '3',
            'domain' => 'sina.com.cn',
            'prelt' => '35',
            'returntype' => 'TEXT',
        ];

        if ($predata['showpin'] == 1) {
            if (empty($this->config['doorImgPath'])) {
                throw new \Exception("login doorcode, 需要验证码，请配置验证码信息");
            }
            // 需要验证码
            $randInt = rand(pow(10, (8 - 1)), pow(10, 8) - 1);
            $imgUrl = 'http://login.sina.com.cn/cgi/pin.php?r=' . $randInt . '&s=0&p=' . $predata['pcid'];
            $this->_download($imgUrl, $this->config['doorImgPath']);
            // 保存param信息
            $params['pcid'] = $predata['pcid'];
            Storage::getInstance()->set('Logindata', Weibologin::getUsername($this->userkey), $params);
            throw new \Exception("login doorcode, 请输入验证码信息");
        }

        $response = $this->client->post($this->loginUrl, ['form_params' => $params]);
        $result = $response->getBody()->getContents();
        $result = json_decode($result, true);
        if ($result['retcode'] != 0) {
            throw new \Exception('get redirect url failed, response content: ' . json_encode($result));
        }
        return $result['crossDomainUrlList'][0];
    }

    private function loginSecond($url)
    {
        // 发起第二次登录请求，再次获取登录请求跳转页arrURL
        $response = $this->client->get($url);
        $json = str_replace(['(', ')', ';'], '', $response->getBody()->getContents());
        return json_decode($json, true);
    }

    public static function getUsername($username)
    {
        return base64_encode($username);
    }

    private function getPassword($password, $servertime, $nonce, $pubkey)
    {
        // 对密码进行rsa加密
        $rsa = new RSA();
        $rsa->loadKey([
            'n' => new BigInteger($pubkey, 16),
            'e' => new BigInteger('10001', 16),
        ]);

        $message = $servertime . "\t" . $nonce . "\n" . $password;

        $rsa->setEncryptionMode(2);
        return bin2hex($rsa->encrypt($message));
    }

    private function getPrelogindata($su)
    {
        // 通过su参数发起第一次请求，获取pubkey和nonce的值
        $url = 'https://login.sina.com.cn/sso/prelogin.php';
        $timestamp = time() * 1000;
        $params = [
            'entry' => 'cnmail',
            'callback' => 'sinaSSOController.preloginCallBack',
            'su' => $su,
            'rsakt' => 'mod',
            'checkpin' => '1',
            'client' => 'ssologin.js(v1.4.19)',
            '_' => $timestamp
        ];
        $url .= '?' . http_build_query($params);
        $response = $this->client->get($url);
        $result = $response->getBody()->getContents();
        $s = strpos($result, '{');
        $e = strpos($result, '}');
        $jsonData = substr($result, $s, ($e - $s + 1));
        return json_decode($jsonData, true);
    }
}
