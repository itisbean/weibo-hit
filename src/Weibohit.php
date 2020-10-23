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

    /**
     * @var array 单例对象
     */
    protected static $_instance = [];


    /**
     * @param array $config ['username' => '', 'password' => '', 'energyUrl' => '', 'suid' => '', proxy => '', 'doorImgPath' => '']
     * @return Weibohit
     */
    public static function init($config = [])
    {
        // 通过base64编码获取su的值
        $userkey = Weibologin::getUsername($config['username']);
        if (!isset(self::$_instance[$userkey])) {
            self::$_instance[$userkey] = new self($config);
        }
        return self::$_instance[$userkey];
    }


    protected function __construct($config = [])
    {
        foreach ($config as $key => $val) {
            $this->$key = $val;
        }

        $this->header = [
            'Referer' => $this->energyUrl,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36'
        ];

        // 保存用户配置信息
        $userkey = Weibologin::getUsername($config['username']);
        Storage::getInstance()->set('Config', $userkey, $config);

        $this->_loginClient = new Weibologin($this->username);
    }

    private function _login()
    {
        // 登录
        try {
            // 检查加油卡返回成功说明已经登录，直接返回
            if (!$this->_checkSpt()) {
                $this->_loginClient->login($this->password, $this->energyUrl);
            }
        } catch (\Exception $e) {
            // echo $e->getMessage() . "\n";
            $this->_loginerror = $e->getMessage();
            return false;
        }
        return true;
    }

    public function relogin($doorcode)
    {
        // 登录
        try {
            $this->_loginClient->relogin($doorcode, $this->energyUrl);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
        return $this->success();
    }

    private function _checkSpt()
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
        $return = ['ret' => true, 'data' => $data, 'msg' => 'success'];
        // echo json_encode($return, JSON_UNESCAPED_UNICODE) . "\n";
        return $return;
    }

    private function error($msg = '')
    {
        $return = ['ret' => false, 'msg' => $msg, 'data' => null];
        // echo json_encode(['ret' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE) . "\n";
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

    /**
     * 查看榜单（实际上这个接口不需要登录）
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

        try {
            $response = $this->_loginClient->client->get($url, ['headers' => $this->header]);
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            return $this->success($result);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
    }

    /**
     * 送加油卡
     * @param integer $num 送卡数量
     * @param string $text 发送微博的文字内容
     * @return array
     */
    public function incrspt($num = 1, $text = '')
    {
        if (!$this->_login()) {
            return $this->error("login failed: " . $this->_loginerror);
        }
        if ($this->spt == -1 && !$this->_checkSpt()) {
            return $this->error('加油卡信息获取失败');
        }
        if ($this->spt == 0) {
            return $this->error('今日加油卡已用完');
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

        try {
            $response = $this->_getclient()->post($url, ['headers' => $this->_getheader(), 'form_params' => $params]);
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            if ($result['code'] != '100000') {
                return $this->error($result['msg']);
            }
            return $this->success();
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
    }

    /**
     * 发微博
     * @param string $text 发送微博的文字内容
     * @return array
     */
    public function post($text)
    {
        if (!$this->_login()) {
            return $this->error("login failed: " . $this->_loginerror);
        }
        $url = 'https://m.weibo.cn/mblog';
        $ext = 'eid:' . $this->_geteid() . '|suid:' . $this->_getsuid() . '|from:tvenergy_index_star';
        $params = [
            'content' => $text,
            'callback' => $this->energyUrl,
            // 'luicode' => '40000095', // TODO
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

        try {
            $response = $this->_getclient()->post($url, [
                'form_params' => $params
            ]);
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            if ($result['ok'] != 1) {
                return $this->error($result['msg']);
            }
            return $this->success($result['id']);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
    }

    /**
     * 转发
     * @param string $mid 原贴ID
     * @param string $text 发送微博的文字内容
     * @return array
     */
    public function repost($mid, $text = '')
    {
        if (!$this->_login()) {
            return $this->error("login failed: " . $this->_loginerror);
        }
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

        try {
            $response = $this->_getclient()->post($url, [
                'headers' => $this->_getheader(['Referer' => $referer]),
                'form_params' => $params
            ]);
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            if ($result['code'] != '100000') {
                return $this->error($result['msg']);
            }
            return $this->success();
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
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
     * 评论帖子
     * @param string $mid 贴子ID
     * @param string $text 评论内容
     * @return array
     */
    public function comment($mid, $text = '')
    {
        if (!$this->_login()) {
            return $this->error("login failed: " . $this->_loginerror);
        }

        // $url = "https://weibo.com/aj/v6/comment/add?ajwvr=6&__rnd=1602694412076";
        $st = $this->_getst();
        if (!$st) {
            return $this->error('get st failed.');
        }
        $url = 'https://m.weibo.cn/api/comments/create';

        try {
            $response = $this->_getclient()->post($url, [
                'form_params' => [
                    'mid' => $mid,
                    'content' => $text,
                    'st' => $st
                ]
            ]);
            $result = $response->getBody()->getContents();
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
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
    }

    /**
     * 点赞
     * @param string $mid 贴子ID
     * @return array
     */
    public function like($mid)
    {
        if (!$this->_login()) {
            return $this->error("login failed: " . $this->_loginerror);
        }

        $rnd = microtime(true) * 1000;
        $url = 'https://www.weibo.com/aj/v6/like/add?ajwvr=6&__rnd=' . $rnd;
        $params = [
            'location' => 'page_100306_home',
            'version' => 'mini',
            'qid' => 'heart',
            'mid' => $mid,
            'loc' => 'profile',
            'cuslike' => 1,
            'floating' => 0,
            '_t' => 0
        ];

        try {
            $response = $this->_getclient()->post($url, [
                'headers' => $this->_getheader(['Referer' => 'https://www.weibo.com']),
                'form_params' => $params
            ]);
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            if ($result['code'] != '100000') {
                return $this->error($result['msg']);
            }
            $data = $result['data'];
            $action = $data['is_del'] ? '取消点赞' : '点赞';
            return $this->success(['is_del' => $data['is_del'], 'action' => $action]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
    }

    /**
     * 超话签到
     * @param string $tid 超话ID
     * @return array
     */
    public function topicSign($tid)
    {
        if (!$this->_login()) {
            return $this->error("login failed: " . $this->_loginerror);
        }

        $url = 'https://weibo.com/p/aj/general/button';
        $params = [
            'ajwvr' => 6,
            'api' => 'http://i.huati.weibo.com/aj/super/checkin',
            'texta' => '签到',
            'textb' => '已签到',
            'status' => '0',
            'id' => $tid,
            'location' => 'page_100808_super_index',
            'timezone' => 'GMT 0800',
            'lang' => 'zh-cn',
            'plat' => 'Win32',
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36',
            'screen' => '1920*1080',
            '__rnd' => microtime(true) * 1000
        ];
        $url .= '?' . http_build_query($params);

        try {
            $response = $this->_getclient()->get($url, [
                'headers' => $this->_getheader(["Referer" => "https://weibo.com/p/{$tid}/super_index"])
            ]);
            $result = $response->getBody()->getContents();
            if (strpos($result, 'location.replace(') !== false) {
                $pattern = '/location.replace\("(.*?)"\)/';
                preg_match($pattern, $result, $matches);
                if ($matches) {
                    $response = $this->_getclient()->get($matches[1]);
                }
                $result = json_decode($result, true);
                if ($result['code'] != '100000') {
                    return $this->error($result['msg']);
                }
                return $this->success($result['data']);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
        return $this->error('unkowns error.');
    }

    /**
     * 超话发贴
     * @param string $tid 超话ID
     * @param string $text 贴子内容
     * @return array
     */
    public function topicPost($tid, $text)
    {
        if (!$this->_login()) {
            return $this->error("login failed: " . $this->_loginerror);
        }

        $rnd = microtime(true) * 1000;
        $url = 'https://weibo.com/p/aj/proxy?ajwvr=6&__rnd=' . $rnd;
        $params = [
            'id' => $tid,
            'domain' => '100808',
            'module' => 'share_topic',
            'title' => '发帖',
            'content' => '',
            'api_url' => 'http://i.huati.weibo.com/pcpage/super/publisher',
            // 'check_url' => "http://i.huati.weibo.com/aj/superpublishauth&pageid={$tid}&uid=" . $this->_loginClient->userinfo['uniqueid'],
            'location' => 'page_100808_super_index',
            'text' => $text,
            'pdetail' => $tid,
            'sync_wb' => 1,
            'isReEdit' => 'false',
            'pub_source' => 'page_2',
            'api' => 'http://i.huati.weibo.com/pcpage/operation/publisher/sendcontent?sign=super&page_id=' . $tid,
            'longtext' => 1,
            // 'topic_id' => '1022:' . $tid,
            'pub_type' => 'dialog',
            '_t' => '0'
        ];


        try {
            $response = $this->_getclient()->post($url, [
                'headers' => $this->_getheader(["Referer" => "https://weibo.com/p/{$tid}/super_index"]),
                'form_params' => $params
            ]);
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            if ($result['code'] != '100000') {
                return $this->error($result['msg']);
            }
            return $this->success($result['data']);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
    }

    /**
     * 微博视频信息
     * @param string $tvurl
     * @return array
     */
    public function getTvinfo($tvurl)
    {
        if (!$this->_login()) {
            return $this->error("login failed: " . $this->_loginerror);
        }

        $basename = pathinfo($tvurl)['basename'];
        $oid = explode('?', $basename)[0];
        $url = "https://weibo.com/tv/api/component?page=/tv/show/" . $oid;
        $data = ['Component_Play_Playinfo' => ['oid' => $oid]];

        try {
            $response = $this->_getclient()->post($url, [
                'headers' => $this->_getheader(['Referer' => $tvurl]),
                'form_params' => ['data' => json_encode($data)]
            ]);
            $result = $response->getBody()->getContents();
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
                'title' => $data['title'],
                'date' => $data['date'],
                'url_short' => $data['url_short']
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $this->error('request failed, error: ' . $e->getMessage());
        }
    }

    /**
     * 获取登录用户ID
     * @return array
     */
    public function getSelf()
    {
        $userinfo = $this->_loginClient->userinfo;
        if (!$userinfo) {
            try {
                $this->_loginClient->login($this->password);
                $userinfo = $this->_loginClient->userinfo;
            } catch (\Exception $e) {
                return $this->error($e->getMessage());
            }
        }
        return $this->success($userinfo);
    }
}
