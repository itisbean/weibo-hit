<?php

namespace Weibohit;

class Weibohit
{

    protected $_loginClient;

    private $username;
    private $password;
    // 能量榜主页
    private $energyUrl;


    public function __construct($config = [])
    {
        foreach ($config as $key => $val) {
            $this->$key = $val;
        }

        $this->_loginClient = Weibologin::init($this->username);

        $this->header = [
            'Referer' => $this->energyUrl,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
        ];

        if (!$this->_checkLogin()) {
            $this->_loginClient->login($this->password, $this->energyUrl);
        }
    }

    private function _checkLogin()
    {
        $url = "https://energy.tv.weibo.cn/aj/checkspt?suid=" . $this->_getsuid() . "&eid=" . $this->_geteid();
        $response = $this->_loginClient->client->get($url, ['headers' => $this->header]);
        $result = $response->getBody()->getContents();
        if ($result['code'] == '100000') {
            $this->spt = $result['data'];
            return true;
        }
        $this->spt = 0;
        return false;
    }

    private function _return($code = 0, $data = [], $msg = '')
    {
        $return = json_encode(['code' => $code, 'data' => $data, 'msg' => $msg]);
        echo $return . "\n";
        return $return;
    }

    private function _geteid()
    {
        if (!isset($this->energyUrl)) {
            return 0;
        }
        $pattern = '/e\/.*\//';
        preg_match($pattern, $this->energyUrl, $matches);
        return str_replace(['e', '/'], '', $matches[0]);
    }

    private function _getsuid()
    {
        return isset($this->suid) ? $this->suid : 0;
    }

    private function _getRandContent()
    {
        return '@容祖儿 我在#我们的歌#嗨歌榜为你助力嗨歌值啦！ http://t.cn/A6bihmsX 1111111111111111111111111111111';
    }

    /**
     * 查看榜单（实际上这个接口不需要登录）
     * @param integer $suid 榜单明星uid
     * @param integer $type 0.默认24小时 大于0为n天
     * @return array
     */
    public function getRank($suid, $type = 0)
    {
        if ($type > 0) {
            // n天
            $url = "https://energy.tv.weibo.cn/aj/trend?suid=" . $this->_getsuid() . "&eid=" . $this->_geteid() . "&type=trend_days&count=" . $type;
        } else {
            // 默认24小时
            $url = "https://energy.tv.weibo.cn/aj/trend?suid=" . $this->_getsuid() . "&eid=" . $this->_geteid() . "&type=trend_hours&count=24";
        }
        $response = $this->_loginClient->client->get($url, ['headers' => $this->header]);

        $result = $response->getBody()->getContents();
        $result = json_decode($result, true);

        if ($response->getStatusCode() != 200) {
            return $this->_return(-1, $result);
        }
        return $this->_return(0, $result);
    }

    /**
     * 送加油卡
     * @return bool
     */
    public function incrspt()
    {
        $client = $this->_loginClient->client;

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
            echo '1------' . $url . "\n";
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
            echo '3------' . $result . "\n";

            // 加油
            $url = 'https://energy.tv.weibo.cn/aj/incrspt';
            $response = $client->post($url, [
                'headers' => [
                    'Referer' => 'https://energy.tv.weibo.cn/e/10574/index',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
                ],
                'form_params' => [
                    'eid' => '10574',
                    'suid' => '1303977362',
                    'spt' => '1',
                    'send_wb' => '1',
                    'send_text' => '@容祖儿  我在#我们的歌#嗨歌榜为你助力啦！22222222222222222222222222',
                    'follow_uid' => '',
                    'page_type' => 'tvenergy_index_star'
                ]
            ]);
            $result = $response->getBody()->getContents();
            echo '4------' . $result . "\n";
        }
    }

    public function post($text = '')
    {
        $client = $this->_loginClient->client;

        $text .= $this->_getRandContent(); 

        $url = "https://m.weibo.cn/mblogDeal/addAMblog";
        $params = [
            'content' => $text,
            'annotations' => ['source' => ['name' => 'tv_energy', 'appid' => '3059977073', 'url' => $this->energyUrl]],
            'st' => 'a0a4cd'
        ];
        $response = $client->post($url, ['form_params' => $params]);
        $result = $response->getBody()->getContents();

        if ($response->getStatusCode() != 200 ) {
            
        }
        echo '2------ ' . $result . "\n";
    }

    public function repost()
    {
    }

    public function comment()
    {
    }

    public function like()
    {
    }
}
