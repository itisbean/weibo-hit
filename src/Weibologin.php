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
     * @var array 单例对象
     */
    protected static $_instance = [];

    /**
     * @var string|null
     */
    protected $_userkey;

    /**
     * @var string|null
     */
    protected $userinfo;

    /**
     * @var Client|null
     */
    protected $client;

    public function __get($name)
    {
        return isset($this->$name) ? $this->$name : null;
    }

    /**
     * @param null $username
     * @return Weibologin
     */
    public static function init($username)
    {
        // 通过base64编码获取su的值
        $userkey = self::getUsername($username);
        if (!isset(self::$_instance[$userkey])) {
            self::$_instance[$userkey] = new self($userkey);
        }
        return self::$_instance[$userkey];
    }

    protected function __construct($userkey)
    {
        // TODO proxy
        $cookie = new FileCookieJar(__DIR__ . '/cookies/' . $userkey, true);
        $this->client = new Client([
            'headers' => [
                'Referer' => 'https://mail.sina.com.cn/?from=mail',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
            ],
            'cookies' => $cookie
        ]);
        $this->_userkey = $userkey;
    }

    public function login($password, $energyUrl = '')
    {
        // 邮箱登录->微博登录->带登录信息进入能量榜
        $predata = $this->getPrelogindata($this->_userkey);
        // echo json_encode($predata) . "\n";
        $loginUrl = $this->loginFirst($predata, $password);
        $userinfo = $this->loginSecond($loginUrl);
        $this->userinfo = $userinfo['userinfo'];
        if ($energyUrl) {
            $this->loginEnergy($energyUrl);
        }
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
            $result = ['code' => $response->getStatusCode(),'content'=>$response->getBody()->getContents()];
            // echo json_encode($result)."\n";
        }
    }

    private function loginFirst($predata, $password)
    {
        // 发起第一次登录请求，获取登录请求跳转页redirect_login_url
        $url = 'https://login.sina.com.cn/sso/login.php?client=ssologin.js(v1.4.19)';
        $params = [
            'su' => $this->_userkey,
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
        $response = $this->client->post($url, ['form_params' => $params]);
        if ($response->getStatusCode() != 200) {
            throw new \Exception("login first request failed, http code: " . $response->getStatusCode());
        }
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
        // echo $response->getBody()->getContents()."\n";
        $json = str_replace(['(', ')', ';'], '', $response->getBody()->getContents());
        return json_decode($json, true);
    }

    private static function getUsername($username)
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
