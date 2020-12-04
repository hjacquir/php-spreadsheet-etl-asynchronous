<?php

namespace Hj\Handler;

use Hj\File\RowAdapter;
use Monolog\Logger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Class RowValidationHandler
 * @package Hj\Handler
 */
class RowValidationHandler implements MessageHandlerInterface
{
    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * RowValidationHandler constructor.
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param RowAdapter $rowAdapter
     */
    public function __invoke(RowAdapter $rowAdapter)
    {
        $this->logger->info("Validation started for : {$rowAdapter->getIndex()}");
        sleep(5);
        $this->logger->info("Validation finished for : {$rowAdapter->getIndex()}");
    }
}