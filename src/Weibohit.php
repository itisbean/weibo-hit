<?php

namespace Weibohit;

class Weibohit {

    protected $_loginUser;

    public function __construct($username, $passward)
    {
        $this->_loginUser = Weibologin::init($username, $passward);
        $this->_loginUser->login($passward);
    }

    public function ourSongTvhit()
    {
        $client = $this->_loginUser->client;
        $url = 'https://energy.tv.weibo.cn/e/10574/index';
        $response = $client->get($url);
        $content = $response->getBody()->getContents();
        echo $content . "\n";
        die;
    }
}