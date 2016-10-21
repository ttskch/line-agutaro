<?php
require_once __DIR__ . '/vendor/autoload.php';

use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\Event\JoinEvent;
use LINE\LINEBot\Event\LeaveEvent;
use LINE\LINEBot\Event\UnfollowEvent;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();

$app->post('/callback', function (Request $request) use ($app) {

    $redis = new Predis\Client(getenv('REDIS_URL'));

    $httpClient = new CurlHTTPClient(getenv('LINE_CHANNEL_ACCESS_TOKEN'));
    $bot = new LINEBot($httpClient, ['channelSecret' => getenv('LINE_CHANNEL_SECRET')]);

    $signature = $request->headers->get(HTTPHeader::LINE_SIGNATURE);
    $body = $request->getContent();

    $events = $bot->parseEventRequest($body, $signature);

    $delete = function ($key, $value) use ($redis) {
        $redis->lrem($key, 0, $value);
    };

    $save = function ($key, $value) use ($redis, $delete) {
        $delete($key, $value);
        $redis->rpush($key, $value);
    };

    // store target (group|room|user)s on redis.
    foreach ($events as $event) {
        if ($event instanceof JoinEvent) {
            if ($event->isGroupEvent()) {
                $save('groups', $event->getGroupId());
            } else {
                $save('rooms', $event->getRoomId());
            }
        } elseif ($event instanceof FollowEvent) {
            $save('users', $event->getUserId());
        } elseif ($event instanceof LeaveEvent) {
            if ($event->isGroupEvent()) {
                $delete('groups', $event->getGroupId());
            } else {
                $delete('rooms', $event->getRoomId());
            }
        } elseif ($event instanceof UnfollowEvent) {
            $delete('users', $event->getUserId());
        }
    }

    echo 'OK';
});

$app->run();
