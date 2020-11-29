# weibo-hit

Sina weibo api for php. Including login, post, repost, comment, sign topic...

## Composer install

composer require itisbean/weibohit -vvv

<!-- composer dumpautoload -o -->

## Demo

Demo: [https://prettycrazyjoey.cn/oursong?referer=joey](https://prettycrazyjoey.cn/oursong?referer=joey) 左上角➡️拉開

## Usage

```php
// 引入autoload.php（框架中使用不需要）
include_once __DIR__.'/../vendor/autoload.php';
$whitInstance = \Weibohit\Weibohit::init([
    'username' => 'your account',
    'password' => 'your password',
    'doorImgPath' => 'if need door code, the door image would be saved here'
]);
// 發微博
$ret = $whitInstance->post('I am a robot');
// 評論
$whitInstance->comment($ret['data'], 'right');
```

## Function

### post

```php
/**
 * 发微博
 * @param string $text 发送微博的文字内容
 * @return array
 */
$whitInstance->post($text);
```

### repost

```php
/**
 * 转发
 * @param string $mid 原贴ID
 * @param string $text 发送微博的文字内容
 * @return array
 */
public function repost($mid, $text = '');
```

### comment from pc

```php
/**
 * 评论帖子
 * @param string $mid 贴子ID
 * @param string $text 评论内容
 * @return array
 */
public function comment($mid, $text = '');
```

### Comment from mobile

```php
/**
 * 移動端評論帖子
 * @param string $mid
 * @param string $text
 * @param boolean $istry
 * @return array
 */
public function mComment($mid, $text, $istry = false);
```

### like or cancel like

```php
/**
 * 点赞或取消
 * @param string $mid 贴子ID
 * @return array
 */
public function like($mid);
```

### sign in the super topic

```php
/**
 * 超话签到
 * @param string $tid 超话ID
 * @return array
 */
public function topicSign($tid);
```

### post in the super topic

```php
/**
 * 超话发贴
 * @param string $tid 超话ID
 * @param string $text 贴子内容
 * @return array
 */
public function topicPost($tid, $text);
```

### get tv info

```php
/**
 * 微博视频信息
 * @param string $tvurl
 * @return array
 */
public function getTvinfo($tvurl);
```

### get self info

```php
/**
 * 获取登录用户ID
 * @return array
 */
public function getSelf()
```

### send incrspt cards

```php
/**
 * 送加油卡
 * @param integer $num 送卡数量
 * @param string $text 发送微博的文字内容
 * @return array
 */
public function incrspt($num = 1, $text = '');
```
