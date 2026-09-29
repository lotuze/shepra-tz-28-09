<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'orm:validate-schema', description: 'Validate ORM mappings and database schema.')]
final class ValidateSchemaCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $validator = new SchemaValidator($this->entityManager);
        $mappingErrors = $validator->validateMapping();

        if ($mappingErrors !== []) {
            foreach ($mappingErrors as $class => $errors) {
                $io->error($class . ': ' . implode(' ', $errors));
            }

            return Command::FAILURE;
        }

        if (!$validator->schemaInSyncWithMetadata()) {
            $io->error('Database schema is not in sync with ORM metadata.');
            foreach ($validator->getUpdateSchemaList() as $sql) {
                $io->writeln($sql);
            }

            return Command::FAILURE;
        }

        $io->success('Mapping is valid and the database schema is in sync.');
        return Command::SUCCESS;
    }
}
