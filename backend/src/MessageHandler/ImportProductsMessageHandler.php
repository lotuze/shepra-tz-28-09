<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Import\ImportProcessor;
use App\Message\ImportProductsMessage;
use Psr\Log\LoggerInterface;

final class ImportProductsMessageHandler
{
    public function __construct(private readonly ImportProcessor $processor, private readonly LoggerInterface $logger) {}

    public function __invoke(ImportProductsMessage $message): void
    {
        $this->logger->info('Starting import job {id}.', ['id' => $message->importJobId]);
        $this->processor->process($message->importJobId);
        $this->logger->info('Finished import job {id}.', ['id' => $message->importJobId]);
    }
}
