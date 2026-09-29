<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\ProductFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fixtures:load', description: 'Purge product data and load deterministic fixtures.')]
final class FixturesLoadCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->isInteractive() && !$io->confirm('Purge existing product data and load fixtures?', false)) {
            $io->warning('Fixtures were not loaded.');
            return Command::SUCCESS;
        }

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('ALTER TABLE products ALTER COLUMN id RESTART WITH 1');
        $connection->executeStatement('ALTER TABLE product_attributes ALTER COLUMN id RESTART WITH 1');
        $connection->executeStatement('ALTER TABLE product_images ALTER COLUMN id RESTART WITH 1');

        $executor = new ORMExecutor($this->entityManager, $purger);
        $executor->execute([new ProductFixtures()], append: true);
        $io->success('Loaded 30 products with deterministic attributes and images.');

        return Command::SUCCESS;
    }
}
