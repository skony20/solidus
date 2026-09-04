<?php

declare(strict_types=1);

use Yiisoft\Queue\Redis\QueueProvider;
use Yiisoft\Queue\Redis\QueueProviderInterface;

/**
 * Kolejka na Redisie.
 *
 * Zadania w tle to na razie glownie masowe wysylki e-mail (modul Komunikacja)
 * i odswiezanie scoringu AML. Bez kolejki wysylka do 300 klientow blokowalaby
 * zadanie HTTP na kilka minut.
 *
 * Wymaga rozszerzenia ext-redis, ktore instaluje obraz PHP z docker/Dockerfile.php.
 *
 * @var array $params
 */

$redis = $params['solidus']['redis'];

return [
    Redis::class => static function () use ($redis): Redis {
        $connection = new Redis();
        $connection->connect($redis['host'], $redis['port']);

        return $connection;
    },

    QueueProviderInterface::class => [
        'class' => QueueProvider::class,
        '__construct()' => [
            'channelName' => 'solidus',
        ],
    ],
];
