<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportError;
use App\Entity\ImportJob;
use App\Repository\ImportErrorRepository;
use App\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ImportProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportJobRepository $jobs,
        private readonly ImportErrorRepository $errors,
        private readonly SpreadsheetReader $reader,
        private readonly ProductRowMapper $mapper,
        private readonly ProductImportService $products,
        private readonly LoggerInterface $logger,
        private readonly string $storagePath,
    ) {}

    public function process(int $jobId): void
    {
        $job = $this->jobs->findById($jobId);
        if ($job === null || in_array($job->getStatus(), [ImportJob::COMPLETED, ImportJob::COMPLETED_WITH_ERRORS, ImportJob::FAILED], true)) {
            return;
        }
        try {
            $restarted = $job->getStatus() === ImportJob::PROCESSING;
            if ($restarted) {
                $this->logger->warning('Recovering processing import job after message redelivery.', ['importJobId' => $jobId]);
                $job->restart();
                $this->entityManager->flush();
                $this->errors->deleteForJob($job);
                $this->entityManager->clear();
                $job = $this->jobs->findById($jobId) ?? throw new \RuntimeException('Import job disappeared.');
            }

            $spreadsheet = $this->reader->read($this->storagePath . '/' . $job->getStoredPath());
            if ($restarted) {
                $job->setTotalRows(count($spreadsheet->rows));
            } else {
                $job->start(count($spreadsheet->rows));
            }
            $this->entityManager->flush();
            $hasErrors = false;
            foreach ($spreadsheet->rows as $source) {
                $raw = $source['data'];
                $externalCode = isset($raw['Внешний код']) && is_scalar($raw['Внешний код']) ? trim((string) $raw['Внешний код']) : null;
                try {
                    $row = $this->mapper->map($raw);
                    $result = $this->products->import($row);
                    $job = $this->jobs->findById($jobId) ?? throw new \RuntimeException('Import job disappeared.');
                    $job->recordSuccess();
                    foreach ($result->warnings as $warning) {
                        $hasErrors = true;
                        $this->entityManager->persist(new ImportError($job, ImportError::WARNING, $warning, $source['rowNumber'], $externalCode));
                    }
                } catch (\Throwable $exception) {
                    $hasErrors = true;
                    $job = $this->jobs->findById($jobId) ?? throw new \RuntimeException('Import job disappeared.');
                    $job->recordFailure();
                    $this->entityManager->persist(new ImportError($job, ImportError::ERROR, $exception->getMessage(), $source['rowNumber'], $externalCode, $this->safeRawData($raw)));
                }
                $this->entityManager->flush();
            }
            $job = $this->jobs->findById($jobId) ?? throw new \RuntimeException('Import job disappeared.');
            $job->complete($hasErrors);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->entityManager->clear();
            $job = $this->jobs->findById($jobId);
            if ($job !== null) { $job->fail($exception->getMessage()); $this->entityManager->flush(); }
        }
    }

    /** @param array<string, mixed> $raw @return array<string, mixed> */
    private function safeRawData(array $raw): array
    {
        $safe = [];
        foreach (array_slice($raw, 0, 50, true) as $key => $value) {
            $safe[mb_substr((string) $key, 0, 100)] = is_scalar($value) ? mb_substr((string) $value, 0, 500) : null;
        }
        return $safe;
    }
}
