<?php

include_once __DIR__.'/../src/Weibohit.php';
include_once __DIR__.'/../src/Weibologin.php';
include_once __DIR__.'/../vendor/autoload.php';

$username = '';
$password = '';

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

    // 登录用户
    // $whitclass->getSelf();

    // 播放信息
    // $tvurl = 'https://weibo.com/tv/show/1034:4559402053861384?from=old_pc_videoshow';
    // $whitclass->getTvinfo($tvurl);

    // 送加油卡
    // $whitclass->incrspt(1);
    
    // 发微博
    $whitclass->post('@容祖儿 我在#我们的歌#嗨歌榜为你助力嗨歌值啦！ http://t.cn/A6bihmsX JOEY!!3');

    // 转发
    // $mid = '4558964769693924';
    // $text = '@容祖儿 我在#我们的歌#嗨歌榜为你助力嗨歌值啦！ 祖儿祖儿祖儿❤';
    // $whitclass->repost($mid, $text);

    // 评论
    // $whitclass->comment('4558980632808172', 'joeyjoey');

    // 点赞

} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
