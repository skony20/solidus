<?php

declare(strict_types=1);

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;
use Yiisoft\Db\Mysql\Dsn;

/** @var array $params */

$db = $params['solidus']['db'];

return [
    ConnectionInterface::class => [
        'class' => Connection::class,
        '__construct()' => [
            'driver' => new Driver(
                (string) new Dsn(
                    host: $db['host'],
                    databaseName: $db['name'],
                    port: $db['port'],
                    options: ['charset' => $db['charset']],
                ),
                $db['user'],
                $db['password'],
            ),
        ],
    ],
];
