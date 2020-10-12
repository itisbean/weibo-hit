<?php

include_once __DIR__.'/../src/Weibohit.php';
include_once __DIR__.'/../src/Weibologin.php';
include_once __DIR__.'/../vendor/autoload.php';

$username = 'douning16@gmail.com';
$password = 'doujy616';

try {
    $whitclass = new \Weibohit\Weibohit($username, $password);
    $whitclass->ourSongTvhit();
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
