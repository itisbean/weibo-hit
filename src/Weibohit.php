<?php

namespace Weibohit;

class Weibohit {

    protected $_loginUser;

    public function __construct()
    {
        // $this->_loginUser = Weibologin::init($username, $passward);
        // $userinfo = $this->_loginUser->login($passward);
        // var_export($userinfo)."\n";
    }

    public function getRank()
    {
        $client = new \GuzzleHttp\Client([
            'headers' => [
                'Referer' => 'https://energy.tv.weibo.cn/e/10574/index',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
            ]
        ]);

        $url = 'https://energy.tv.weibo.cn/aj/trend?suid=1303977362&eid=10574&type=trend_hours&count=24';
        // $url = 'https://energy.tv.weibo.cn/aj/trend?suid=1303977362&eid=10574&type=trend_days&count=7';  //周
        // $url = 'https://energy.tv.weibo.cn/aj/trend?suid=1303977362&eid=10574&type=trend_days&count=30'; //月
        $response = $client->get($url);

        $content = $response->getBody()->getContents();
        echo $content."\n";die;
    }

    public function hitRank($username, $passward)
    {
        $this->_loginUser = Weibologin::init($username, $passward);
        $userinfo = $this->_loginUser->login($passward);
        echo json_encode($userinfo)."\n";

        $client = $this->_loginUser->client;

        $hitIndex = 'https://energy.tv.weibo.cn/e/10574/index';

        $url = 'https://login.sina.com.cn/sso/login.php';
        $params = [
            'url' => $hitIndex,
            '_rand' => '1602591493.2292',
            'gateway' => 1,
            'service' => 'weibo',
            'entry' => 'miniblog',
            'useticket' => '0',
            'returntype' => 'META',
            'sudaref' => '',
            '_client_version' => '0.6.23'
        ];
        $url .= '?' . http_build_query($params);
        $response = $client->get($url);
        $result = $response->getBody()->getContents();
        $pattern = '/\("(.*?)"\)/';
        preg_match($pattern, $result, $matches);
        if ($matches) {
            $url = $matches[1];
            echo '1------'.$url."\n";
            $response = $client->get($url);
            $result = $response->getBody()->getContents();
            // echo '2------'.$result."\n";
            $url = 'https://energy.tv.weibo.cn/aj/checkspt?suid=1303977362&eid=10574';
            $response = $client->get($url, [
                'headers' => [
                    'Referer' => 'https://energy.tv.weibo.cn/e/10574/index',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
                ],
            ]);
            $result = $response->getBody()->getContents();
            echo '3------'.$result."\n";
        }
    }


    public function ourSongTvhit()
    {
        $client = $this->_loginUser->client;
        
        // $url = 'https://energy.tv.weibo.cn/e/10574/index';
        // $response = $client->get($url);
        // $content = $response->getBody()->getContents();
        // file_put_contents(__DIR__.'/energy_1.html', $content);

        // $url = 'https://energy.tv.weibo.cn/aj/checkspt?suid=1303977362&eid=10574';
        $url = 'https://energy.tv.weibo.cn/aj/trend?suid=1303977362&eid=10574&type=trend_hours&count=24';
        $response = $client->get($url, [
            'headers' => [
                'Referer' => 'https://energy.tv.weibo.cn/e/10574/index',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
            ]
        ]);
        $content = $response->getBody()->getContents();
        echo $content."\n";die;
    }


}