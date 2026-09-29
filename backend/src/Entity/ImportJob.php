<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportJobRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportJobRepository::class)]
#[ORM\Table(name: 'import_jobs')]
#[ORM\Index(name: 'idx_import_jobs_status', columns: ['status'])]
class ImportJob
{
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const FAILED = 'failed';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'original_filename', length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'stored_path', length: 1024)]
    private string $storedPath;

    #[ORM\Column(length: 32)]
    private string $status = self::QUEUED;

    #[ORM\Column(name: 'total_rows')]
    private int $totalRows = 0;

    #[ORM\Column(name: 'processed_rows')]
    private int $processedRows = 0;

    #[ORM\Column(name: 'successful_rows')]
    private int $successfulRows = 0;

    #[ORM\Column(name: 'failed_rows')]
    private int $failedRows = 0;

    #[ORM\Column(name: 'fatal_error', type: 'text', nullable: true)]
    private ?string $fatalError = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    /** @var Collection<int, ImportError> */
    #[ORM\OneToMany(mappedBy: 'importJob', targetEntity: ImportError::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $errors;

    public function __construct(string $originalFilename, string $storedPath)
    {
        $this->originalFilename = $originalFilename;
        $this->storedPath = $storedPath;
        $this->createdAt = new DateTimeImmutable();
        $this->errors = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function getStoredPath(): string { return $this->storedPath; }
    public function getStatus(): string { return $this->status; }
    public function getTotalRows(): int { return $this->totalRows; }
    public function getProcessedRows(): int { return $this->processedRows; }
    public function getSuccessfulRows(): int { return $this->successfulRows; }
    public function getFailedRows(): int { return $this->failedRows; }
    public function getFatalError(): ?string { return $this->fatalError; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getStartedAt(): ?DateTimeImmutable { return $this->startedAt; }
    public function getFinishedAt(): ?DateTimeImmutable { return $this->finishedAt; }

    public function start(int $totalRows): void
    {
        if ($this->status !== self::QUEUED) {
            throw new \DomainException('Only a queued import can be started.');
        }
        $this->status = self::PROCESSING;
        $this->totalRows = $totalRows;
        $this->startedAt = new DateTimeImmutable();
    }

    public function restart(): void
    {
        if ($this->status !== self::PROCESSING) {
            throw new \DomainException('Only a processing import can be restarted.');
        }
        $this->totalRows = 0;
        $this->processedRows = 0;
        $this->successfulRows = 0;
        $this->failedRows = 0;
        $this->fatalError = null;
        $this->startedAt = new DateTimeImmutable();
        $this->finishedAt = null;
    }

    public function setTotalRows(int $totalRows): void
    {
        $this->assertProcessing();
        if ($totalRows < 0) {
            throw new \InvalidArgumentException('Total rows cannot be negative.');
        }
        $this->totalRows = $totalRows;
    }

    public function recordSuccess(): void
    {
        $this->assertProcessing();
        ++$this->processedRows;
        ++$this->successfulRows;
    }

    public function recordFailure(): void
    {
        $this->assertProcessing();
        ++$this->processedRows;
        ++$this->failedRows;
    }

    public function complete(bool $hasErrors): void
    {
        $this->assertProcessing();
        $this->status = $hasErrors ? self::COMPLETED_WITH_ERRORS : self::COMPLETED;
        $this->finishedAt = new DateTimeImmutable();
    }

    public function fail(string $message): void
    {
        if (in_array($this->status, [self::COMPLETED, self::COMPLETED_WITH_ERRORS], true)) {
            throw new \DomainException('A completed import cannot fail.');
        }
        $this->status = self::FAILED;
        $this->fatalError = mb_substr($message, 0, 2000);
        $this->finishedAt = new DateTimeImmutable();
    }

    public function progress(): int
    {
        if (in_array($this->status, [self::COMPLETED, self::COMPLETED_WITH_ERRORS], true)) {
            return 100;
        }
        return $this->status === self::PROCESSING && $this->totalRows > 0
            ? (int) floor($this->processedRows / $this->totalRows * 100)
            : 0;
    }

    private function assertProcessing(): void
    {
        if ($this->status !== self::PROCESSING) {
            throw new \DomainException('Import is not processing.');
        }
    }
}
