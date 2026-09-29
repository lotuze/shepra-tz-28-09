<?php

declare(strict_types=1);

namespace App\Command;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

#[AsCommand(name: 'messenger:consume', description: 'Consume messages from an async transport.')]
final class MessengerConsumeCommand extends Command
{
    private ?Worker $worker = null;

    public function __construct(private readonly TransportInterface $transport, private readonly MessageBusInterface $bus, private readonly LoggerInterface $logger, private readonly EventDispatcherInterface $dispatcher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('receiver', InputArgument::OPTIONAL, 'Receiver name', 'async');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getArgument('receiver') !== 'async') {
            throw new \InvalidArgumentException('Only the async receiver is configured.');
        }
        $this->worker = new Worker(['async' => $this->transport], $this->bus, $this->dispatcher, $this->logger);
        $this->logger->info('Messenger worker started for async transport.');
        $this->worker->run(['sleep' => 1_000_000]);
        $this->logger->info('Messenger worker stopped.');
        $this->worker = null;
        return Command::SUCCESS;
    }

    public function getSubscribedSignals(): array
    {
        return [SIGTERM, SIGINT];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->logger->info('Messenger worker received shutdown signal.', ['signal' => $signal]);
        $this->worker?->stop();

        return false;
    }
}
