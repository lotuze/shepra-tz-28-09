<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportErrorRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportErrorRepository::class)]
#[ORM\Table(name: 'import_errors')]
#[ORM\Index(name: 'idx_import_errors_job', columns: ['import_job_id'])]
class ImportError
{
    public const ERROR = 'error';
    public const WARNING = 'warning';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'errors')]
    #[ORM\JoinColumn(name: 'import_job_id', nullable: false, onDelete: 'CASCADE')]
    private ImportJob $importJob;

    #[ORM\Column(name: 'row_number', nullable: true)]
    private ?int $rowNumber;

    #[ORM\Column(name: 'external_code', length: 255, nullable: true)]
    private ?string $externalCode;

    #[ORM\Column(length: 16)]
    private string $severity;

    #[ORM\Column(type: 'text')]
    private string $message;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'raw_data', type: 'json', nullable: true)]
    private ?array $rawData;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /** @param array<string, mixed>|null $rawData */
    public function __construct(ImportJob $job, string $severity, string $message, ?int $rowNumber = null, ?string $externalCode = null, ?array $rawData = null)
    {
        if (!in_array($severity, [self::ERROR, self::WARNING], true)) {
            throw new \InvalidArgumentException('Invalid import error severity.');
        }
        $this->importJob = $job;
        $this->severity = $severity;
        $this->message = mb_substr($message, 0, 2000);
        $this->rowNumber = $rowNumber;
        $this->externalCode = $externalCode === null ? null : mb_substr($externalCode, 0, 255);
        $this->rawData = $rawData;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }
    public function getExternalCode(): ?string
    {
        return $this->externalCode;
    }
    public function getSeverity(): string
    {
        return $this->severity;
    }
    public function getMessage(): string
    {
        return $this->message;
    }
    public function getRawData(): ?array
    {
        return $this->rawData;
    }
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
