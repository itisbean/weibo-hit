<?php

include_once __DIR__.'/../src/Weibohit.php';
include_once __DIR__.'/../src/Weibologin.php';
include_once __DIR__.'/../vendor/autoload.php';

$username = '';
$password = '';

try {
    // $whitclass = new \Weibohit\Weibohit($username, $password);
    $whitclass = new \Weibohit\Weibohit();
    $whitclass->hitRank($username, $password);
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
