<?php

use Doctrine\DBAL\DriverManager;
use Hj\File\RowAdapter;
use Hj\Handler\RowValidationHandler;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceiver;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Worker;

require_once "vendor/autoload.php";

//@todo begin encapsulate
$logger = new \Monolog\Logger("console");
$logger->setTimezone(new DateTimeZone("Europe/Paris"));
$output = "[%datetime%] %level_name% : %message%\n";
$formatter = new \Monolog\Formatter\LineFormatter($output, "d/m/Y H:i:s");

try {
    $streamHandler = new \Monolog\Handler\StreamHandler('php://stdout');
    $rotatingHandler = new \Monolog\Handler\RotatingFileHandler("logs/spreadsheet-etl.log");
    $streamHandler->setFormatter($formatter);
    $rotatingHandler->setFormatter($formatter);
    $logger->pushHandler($streamHandler);
    $logger->pushHandler($rotatingHandler);
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
//@todo end encapsulate
//@todo begin encapsulate
$doctrineDbalConnection = DriverManager::getConnection([
    'url' => 'sqlite:///db/test.sqlite',
]);
$transportDoctrineConnection = new Connection(
    [
        'connection' => 'doctrine://default',
    ],
    $doctrineDbalConnection
);
//@todo end encapsulate

$bus = new MessageBus([
        new HandleMessageMiddleware(new HandlersLocator(
                [
                    RowAdapter::class => [
                        new RowValidationHandler($logger),
                    ],
                ]
            )
        ),
    ]
);

$receiver = new DoctrineReceiver($transportDoctrineConnection);
$worker = new Worker([$receiver], $bus, null);
$worker->run();