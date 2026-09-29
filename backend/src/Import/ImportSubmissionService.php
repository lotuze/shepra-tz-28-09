<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportJob;
use App\Message\ImportProductsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportSubmissionService
{
    private const MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly string $storagePath,
        private readonly int $maxFileSize,
    ) {
    }

    public function submit(UploadedFileInterface $file): ImportJob
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('File upload failed.');
        }
        $originalName = $file->getClientFilename() ?? '';
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new \InvalidArgumentException('Only XLSX files are allowed.');
        }
        $size = $file->getSize();
        if ($size <= 0 || $size > $this->maxFileSize) {
            throw new \InvalidArgumentException('Uploaded file size is invalid.');
        }
        $temporaryPath = $file->getStream()->getMetadata('uri');
        if (!is_string($temporaryPath)) {
            throw new \InvalidArgumentException('Unable to inspect uploaded file.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!in_array($mime, self::MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Uploaded file MIME type is not allowed.');
        }

        $relative = 'imports/' . bin2hex(random_bytes(20)) . '.xlsx';
        $absolute = $this->storagePath . '/' . $relative;
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0775, true) && !is_dir(dirname($absolute))) {
            throw new \RuntimeException('Unable to create import storage directory.');
        }
        try {
            $file->moveTo($absolute);
            $job = new ImportJob(mb_substr($originalName, 0, 255), $relative);
            $this->entityManager->persist($job);
            $this->entityManager->flush();
            try {
                $this->bus->dispatch(new ImportProductsMessage($job->getId()));
            } catch (\Throwable $exception) {
                $this->entityManager->remove($job);
                $this->entityManager->flush();
                throw $exception;
            }
            return $job;
        } catch (\Throwable $exception) {
            if (is_file($absolute)) {
                unlink($absolute);
            }
            throw $exception;
        }
    }
}
