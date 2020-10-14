<?php

namespace Weibohit;

use GuzzleHttp\Client;

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
        $result = json_decode($result, true);
        if ($result && $result['code'] == '100000') {
            $this->spt = $result['data'];
            return true;
        }
        $this->spt = -1;
        return false;
    }

    private function success($data = [])
    {
        $return = json_encode(['ret' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        echo $return . "\n";
        return $return;
    }

    private function error($msg = '')
    {
        $return = json_encode(['ret' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
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

    private function _getclient()
    {
        if ($this->_loginClient->client instanceof Client) {
            return $this->_loginClient->client;
        }
        return new Client(['headers' => $this->header]);
    }

    private function _getheader($array = [])
    {
        $header = $this->header;
        if ($array) {
            foreach ($array as $key => $val) {
                $header[$key] = $val;
            }
        }
        return $header;
    }

    private function _getst()
    {
        $url = 'https://m.weibo.cn/api/config';
        $response = $this->_getclient()->get($url);
        $result = $response->getBody()->getContents();
        $result = json_decode($result, true);
        if (!$result || $result['ok'] != 1 || !$result['data']['login']) {
            return false;
        }
        return $result['data']['st'];
    }

    /**
     * 查看榜单（实际上这个接口不需要登录）
     * @param integer $suid 榜单明星uid
     * @param integer $type 0.默认24小时 大于0为n天
     * @return array
     */
    public function getRank($type = 0)
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
     * @param integer $num 送卡数量
     * @return bool
     */
    public function incrspt($num = 1, $text = '')
    {
        if ($this->spt == -1 && !$this->_checkLogin()) {
            $this->error('登录失败');
        }
        if ($this->spt == 0) {
            $this->error('今日加油卡已用完');
        }

        // 加油
        $url = 'https://energy.tv.weibo.cn/aj/incrspt';
        $params = [
            'eid' => $this->_geteid(),
            'suid' => $this->_getsuid(),
            'spt' => $num > 0 ? $num : 1,
            'send_wb' => '0',
            'follow_uid' => '',
            'page_type' => 'tvenergy_index_star'
        ];
        if ($text) {
            $params['send_wb']  = '1';
            $params['send_text'] = $text;
        }
        $response = $this->_getclient()->post($url, ['headers' => $this->_getheader(), 'form_params' => $params]);
        $result = $response->getBody()->getContents();

        if ($response->getStatusCode() != '200') {
            return $this->error('request failed, content: ' . $result);
        }
        $result = json_decode($result, true);

        if ($result['code'] != '100000') {
            return $this->error($result['msg']);
        }
        return $this->success();
    }

    /**
     * 发微博
     * @return bool
     */
    public function post($text)
    {
        $url = 'https://m.weibo.cn/mblog';
        $ext = 'eid:' . $this->_geteid() . '|suid:' . $this->_getsuid() . '|from:tvenergy_index_star';
        $params = [
            'content' => $text,
            'callback' => $this->energyUrl,
            'luicode' => '40000095', // TODO
            'extparam' => $ext,
            'ext' => $ext
        ];
        $url .= '?' . http_build_query($params);
        $response = $this->_getclient()->get($url);
        $result = $response->getBody()->getContents();
        $st = substr($result, strpos($result, '"st":'), 13);
        $st = str_replace(['st', ',', ':', '"'], '', $st);
        if (!$st) {
            return $this->error('get st failed.');
        }

        $url = "https://m.weibo.cn/mblogDeal/addAMblog";
        $params = [
            'content' => $text,
            'annotations' => [
                'source' => ['name' => 'tv_energy', 'appid' => '3059977073', 'url' => $this->energyUrl]
            ],
            'st' => $st
        ];
        $response = $this->_getclient()->post($url, [
            'form_params' => $params
        ]);
        $result = $response->getBody()->getContents();

        if ($response->getStatusCode() != '200') {
            return $this->error('request failed, content: ' . $result);
        }
        $result = json_decode($result, true);

        if ($result['ok'] != 1) {
            return $this->error($result['msg']);
        }
        return $this->success($result['id']);
    }

    /**
     * 转发
     * @return void
     */
    public function repost($mid, $text = '')
    {
        $url = 'https://energy.tv.weibo.cn/aj/repost';

        $eid = $this->_geteid();
        $suid = $this->_getsuid();
        $params = [
            'mid' => $mid,
            'text' => $text,
            'follow' => '',
            'eid' => $eid,
            'suid' => $suid,
            'page_type' => 'tvenergy_index_star'
        ];

        $referer = "https://energy.tv.weibo.cn/repost?eid={$eid}&suid={$suid}&page_type=tvenergy_index_star";
        $response = $this->_getclient()->post($url, [
            'headers' => $this->_getheader(['Referer' => $referer]),
            'form_params' => $params
        ]);
        $result = $response->getBody()->getContents();

        if ($response->getStatusCode() != '200') {
            return $this->error('request failed, content: ' . $result);
        }
        $result = json_decode($result, true);

        if ($result['code'] != '100000') {
            return $this->error($result['msg']);
        }
        return $this->success();
    }

    /**
     * 评论帖子
     * @return void
     */
    public function comment($mid, $text = '', $forward = 0)
    {
        // $url = "https://weibo.com/aj/v6/comment/add?ajwvr=6&__rnd=1602694412076";
        $st = $this->_getst();
        if (!$st) {
            return $this->error('get st failed.');
        }

        $url = 'https://m.weibo.cn/api/comments/create';
        $response = $this->_getclient()->post($url, [
            'form_params' => [
                'mid' => $mid,
                'content' => $text,
                'st' => $st
            ]
        ]);
        $result = $response->getBody()->getContents();

        if ($response->getStatusCode() != '200') {
            return $this->error('request failed, content: ' . $result);
        }
        $result = json_decode($result, true);

        if ($result['ok'] != 1) {
            return $this->error($result['msg']);
        }
        $data = $result['data'];
        return $this->success([
            'id' => $data['id'],
            'created_at' => strtotime($data['created_at']),
            'user' => [
                'id' => $data['user']['id'],
                'screen_name' => $data['user']['screen_name']
            ]
        ]);
    }

    public function like($mid)
    {
    }

    public function getTvinfo($tvurl)
    {
        $basename = pathinfo($tvurl)['basename'];
        $oid = explode('?', $basename)[0];
        $url = "https://weibo.com/tv/api/component?page=/tv/show/" . $oid;

        $response = $this->_getclient()->post($url, [
            'headers' => $this->_getheader(['Referer' => $tvurl]),
            'form_params' => [
                'data' => '{"Component_Play_Playinfo":{"oid":' . $oid . '}}'
            ]
        ]);

        $result = $response->getBody()->getContents();
        if ($response->getStatusCode() != '200') {
            return $this->error('request failed, content: ' . $result);
        }
        $result = json_decode($result, true);

        if ($result['code'] != '100000') {
            return $this->error($result['msg']);
        }
        $data = $result['data']['Component_Play_Playinfo'];
        return $this->success([
            'attitudes_count' => $data['attitudes_count'],
            'comments_count' => $data['comments_count'],
            'duration_time' => $data['duration_time'],
            'mid' => $data['mid'],
            'oid' => $data['oid'],
            'play_count' => $data['play_count'],
            'reposts_count' => $data['reposts_count'],
            'title' => $data['title']
        ]);
    }

    public function getSelf()
    {
        $userinfo = $this->_loginClient->userinfo;
        if (!$userinfo) {
            $this->_loginClient->login($this->password);
            $userinfo = $this->_loginClient->userinfo;
        }
        return $this->success($userinfo);
    }
}
