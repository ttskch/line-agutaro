<?php
require_once __DIR__ . '/vendor/autoload.php';

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;

$redis = new Predis\Client(getenv('REDIS_URL'));

$httpClient = new CurlHTTPClient(getenv('LINE_CHANNEL_ACCESS_TOKEN'));
$bot = new LINEBot($httpClient, ['channelSecret' => getenv('LINE_CHANNEL_SECRET')]);

$groups = $redis->lrange('groups', 0, $redis->llen('groups'));
$rooms = $redis->lrange('rooms', 0, $redis->llen('rooms'));
$users = $redis->lrange('users', 0, $redis->llen('users'));

$firebase = new \Firebase\FirebaseLib(getenv('FIREBASE_DATABASE_URL'), getenv('FIREBASE_DATABASE_TOKEN'));

$meigen1 = json_decode($firebase->get('/meigen1'), true);
$meigen2 = json_decode($firebase->get('/meigen2'), true);
$meigen1 = $meigen1[array_rand($meigen1)];
$meigen2 = $meigen2[array_rand($meigen2)];

$text1 = <<<EOT1
本日の名言

{$meigen1['phrase']}

{$meigen1['sayer']}
EOT1;

$text2 = <<<EOT2
本日の迷言

{$meigen2['phrase']}

{$meigen2['sayer']}
EOT2;

$messageBuilder = new MultiMessageBuilder();
$messageBuilder
    ->add(new TextMessageBuilder($text1))
    ->add(new TextMessageBuilder($text2))
;

foreach (array_merge($groups, $rooms, $users) as $target) {
    $bot->pushMessage($target, $messageBuilder);
}
