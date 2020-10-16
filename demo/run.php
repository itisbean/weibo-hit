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
    // $whitInstance = new \Weibohit\Weibohit($username, $password);
    $whitInstance = \Weibohit\Weibohit::init($config);

    // 登录用户
    // $whitInstance->getSelf();

    // 播放信息
    $tvurl = 'https://weibo.com/tv/show/1034:4559402053861384?from=old_pc_videoshow';
    $whitInstance->getTvinfo($tvurl);

    // 送加油卡
    // $whitInstance->incrspt(1);
    
    // 发微博
    // $whitInstance->post('@容祖儿 我在#我们的歌#嗨歌榜为你助力嗨歌值啦！ http://t.cn/A6bihmsX JOEY!!3');

    // 转发
    // $mid = '4558964769693924';
    // $text = '@容祖儿 我在#我们的歌#嗨歌榜为你助力嗨歌值啦！ 祖儿祖儿祖儿❤';
    // $whitInstance->repost($mid, $text);

    // 评论
    // $whitInstance->comment('4558980632808172', 'joeyjoey');

    // 点赞
    // $mid = '4558901184038963';
    // $whitInstance->like($mid);

    // 超话签到
    // $tid = '1008081e1679450760d46693ab925a27a49976';
    // $whitInstance->topicSign($tid);
    // 超话发帖
    // $whitInstance->topicPost($tid, '我可以发帖了吗，我试一下afdfdfdafdfghgfhgjh噢噢噢噢');

} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
