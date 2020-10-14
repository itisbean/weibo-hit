<?php

include_once __DIR__.'/../src/Weibohit.php';
include_once __DIR__.'/../src/Weibologin.php';
include_once __DIR__.'/../vendor/autoload.php';

$username = '';
$password = '';
$url = 'https://energy.tv.weibo.cn/e/10574/index';


try {
    $config = [
        // 登录用户名
        'username' => $username,
        // 密码
        'password' => $password,
        // 能量榜主页
        'energyUrl' => 'https://energy.tv.weibo.cn/e/10574/index',
        // 打榜的明星id
        'suid' => '1303977362'
    ];
    // $whitclass = new \Weibohit\Weibohit($username, $password);
    $whitclass = new \Weibohit\Weibohit($config);
    // 送加油卡
    $whitclass->incrspt();
    $whitclass->post('@容祖儿 我在#我们的歌#嗨歌榜为你助力嗨歌值啦！ http://t.cn/A6bihmsX');
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
