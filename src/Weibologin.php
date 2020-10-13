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
        $cookie = new FileCookieJar(__DIR__ . '/cookie_' . $userkey, true);
        $this->client = new Client([
            'headers' => [
                // 'Referer' => 'https://weibo.com',
                'Referer' => 'https://mail.sina.com.cn/?from=mail',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
            ],
            'cookies' => $cookie
        ]);
        $this->_userkey = $userkey;
    }

    public function login($password)
    {
        $loginUrl = $this->loginFirst($this->_userkey, $password);
        return $this->loginSecond($loginUrl);
    }

    private function loginFirst($userkey, $password)
    {
        $json = $this->getPrelogindata($userkey);
        // TODO remove
        echo json_encode($json) . "\n";
        // 发起第一次登录请求，获取登录请求跳转页redirect_login_url
        $url = 'https://login.sina.com.cn/sso/login.php?client=ssologin.js(v1.4.19)';
        $params = [
            'su' => $userkey,
            'servertime' => $json['servertime'],
            'nonce' => $json['nonce'],
            'sp' => $this->getPassword($password, $json['servertime'], $json['nonce'], $json['pubkey']),
            'rsakv' => $json['rsakv'],
            // 'entry' => 'freemail',
            'entry' => 'cnmail',
            'gateway' => '1',
            'from' => '',
            'savestate' => '30',
            'qrcode_flag' => 'false',
            'useticket' => '0',
            'ssosimplelogin' => '1',
            // 'pagerefer' => "http://login.sina.com.cn/sso/logout.php?entry=miniblog&r=http%3A%2F%2Fweibo.com%2Flogout.php%3Fbackurl",
            'vsnf' => '1',
            // 'service' => 'miniblog',
            'service' => 'sso',
            'pwencode' => 'rsa2',
            'sr' => '1920*1080',
            'encoding' => 'UTF-8',
            'cdult' => '3',
            // 'domain' => '*.weibo.cn',
            'domain' => 'sina.com.cn',
            'prelt' => '35',
            // 'url' => 'https://weibo.com/ajaxlogin.php?framelogin=1&callback=parent.sinaSSOController.feedBackUrlCallBack',
            'returntype' => 'TEXT',
            // 'returntype' => 'META',
        ];
        $response = $this->client->post($url, ['form_params' => $params]);
        if ($response->getStatusCode() != 200) {
            throw new \Exception("login first request failed, http code: " . $response->getStatusCode());
        }
        $result = $response->getBody()->getContents();
        $result = json_decode($result, true);
        // $pattern = '/\("(.*?)"\)/';
        // preg_match($pattern, $result, $matches);
        if ($result['retcode'] != 0) {
            throw new \Exception('get redirect url failed, response content: '. json_encode($result));
        }

        return $result['crossDomainUrlList'][0];
    }

    private function loginSecond($url)
    {
        echo $url."\n";
        // 发起第二次登录请求，再次获取登录请求跳转页arrURL
        $response = $this->client->get($url);
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
