<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Entity\ImportError;
use App\Entity\ImportJob;
use App\Entity\Product;
use App\Import\DecimalNormalizer;
use App\Import\DiscountCalculator;
use App\Import\ImageUrlExtractor;
use App\Import\ImportProcessor;
use App\Import\ProductImportService;
use App\Import\ProductRowMapper;
use App\Import\ProductRowValidator;
use App\Import\SpreadsheetReader;
use App\Repository\ImportErrorRepository;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Tests\TestKernel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ImportProcessorTest extends TestCase
{
    public function testContinuesAfterInvalidRowAndCreatesWarningForImage(): void
    {
        $relative = 'imports/test-mixed.xlsx';
        $absolute = dirname(__DIR__, 2) . '/storage/' . $relative;
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([
            ['Внешний код', 'Наименование', 'Описание', 'Цена: Цена продажи', 'Закупочная цена', 'Доп. поле: Ссылка на упаковку'],
            ['', 'Bad', 'Bad', '100,00', '50,00', ''],
            ['MIXED-OK', 'Good', 'Good description', '100,00', '50,00', 'https://example.test/fail.jpg'],
        ]);
        (new Xlsx($sheet->getParent()))->save($absolute);
        $job = new ImportJob('mixed.xlsx', $relative);
        TestKernel::$entityManager->persist($job);
        TestKernel::$entityManager->flush();
        $this->processor(new FakeImageDownloader(true))->process($job->getId());
        TestKernel::$entityManager->clear();
        $job = TestKernel::$entityManager->find(ImportJob::class, $job->getId());
        self::assertSame(2, $job->getProcessedRows());
        self::assertSame(1, $job->getSuccessfulRows());
        self::assertSame(1, $job->getFailedRows());
        self::assertSame(ImportJob::COMPLETED_WITH_ERRORS, $job->getStatus());
        self::assertSame(2, TestKernel::$entityManager->getRepository(ImportError::class)->count(['importJob' => $job]));
        $product = TestKernel::$entityManager->getRepository(Product::class)->findOneBy(['externalCode' => 'MIXED-OK']);
        self::assertNotNull($product);
        TestKernel::$entityManager->remove($product);
        TestKernel::$entityManager->flush();
        unlink($absolute);
    }

    public function testRealSpreadsheetAndRepeatedImportDoNotDuplicateProducts(): void
    {
        $fixture = dirname(__DIR__) . '/Fixtures/import-example.xlsx';
        $before = TestKernel::$entityManager->getRepository(Product::class)->count([]);
        foreach ([1, 2] as $run) {
            $relative = 'imports/real-' . $run . '.xlsx';
            copy($fixture, dirname(__DIR__, 2) . '/storage/' . $relative);
            $job = new ImportJob('import-example.xlsx', $relative);
            TestKernel::$entityManager->persist($job);
            TestKernel::$entityManager->flush();
            $this->processor(new FakeImageDownloader())->process($job->getId());
            TestKernel::$entityManager->clear();
            $job = TestKernel::$entityManager->find(ImportJob::class, $job->getId());
            self::assertSame(ImportJob::COMPLETED, $job->getStatus());
            self::assertSame(40, $job->getSuccessfulRows());
            $count = TestKernel::$entityManager->getRepository(Product::class)->count([]);
            if ($run === 1) {
                self::assertSame($before + 40, $count);
            } else {
                self::assertSame($before + 40, $count);
            }
            unlink(dirname(__DIR__, 2) . '/storage/' . $relative);
        }
        TestKernel::$entityManager->getConnection()->executeStatement("DELETE FROM products WHERE external_code NOT LIKE 'SKU-%'");
        TestKernel::$entityManager->clear();
    }

    public function testRedeliveredProcessingJobRestartsFromBeginning(): void
    {
        $relative = 'imports/test-redelivery.xlsx';
        $absolute = dirname(__DIR__, 2) . '/storage/' . $relative;
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([
            ['Внешний код', 'Наименование', 'Описание', 'Цена: Цена продажи', 'Закупочная цена'],
            ['REDELIVERY-1', 'Updated product', 'Updated description', '100,00', '75,00'],
        ]);
        (new Xlsx($sheet->getParent()))->save($absolute);

        $product = new Product('REDELIVERY-1', 'Old product', 'Old description', '50.00', '10.00');
        $job = new ImportJob('test-redelivery.xlsx', $relative);
        $job->start(2);
        $job->recordSuccess();
        $job->recordFailure();
        $oldError = new ImportError($job, ImportError::ERROR, 'Old processing error', 2, 'REDELIVERY-1');
        TestKernel::$entityManager->persist($product);
        TestKernel::$entityManager->persist($job);
        TestKernel::$entityManager->persist($oldError);
        TestKernel::$entityManager->flush();
        $jobId = $job->getId();
        $oldErrorId = $oldError->getId();

        $this->processor(new FakeImageDownloader())->process($jobId);
        TestKernel::$entityManager->clear();

        $job = TestKernel::$entityManager->find(ImportJob::class, $jobId);
        self::assertSame(ImportJob::COMPLETED, $job->getStatus());
        self::assertSame(1, $job->getTotalRows());
        self::assertSame(1, $job->getProcessedRows());
        self::assertSame(1, $job->getSuccessfulRows());
        self::assertSame(0, $job->getFailedRows());
        self::assertNull($job->getFatalError());
        self::assertNull(TestKernel::$entityManager->find(ImportError::class, $oldErrorId));
        self::assertSame(0, TestKernel::$entityManager->getRepository(ImportError::class)->count(['importJob' => $job]));
        self::assertSame(1, TestKernel::$entityManager->getRepository(Product::class)->count(['externalCode' => 'REDELIVERY-1']));
        self::assertSame('Updated product', TestKernel::$entityManager->getRepository(Product::class)->findOneBy(['externalCode' => 'REDELIVERY-1'])->getName());

        TestKernel::$entityManager->remove($job);
        TestKernel::$entityManager->remove(TestKernel::$entityManager->getRepository(Product::class)->findOneBy(['externalCode' => 'REDELIVERY-1']));
        TestKernel::$entityManager->flush();
        unlink($absolute);
    }

    private function processor(FakeImageDownloader $images): ImportProcessor
    {
        $em = TestKernel::$entityManager;
        /** @var ImportJobRepository $jobs */ $jobs = $em->getRepository(ImportJob::class);
        /** @var ImportErrorRepository $errors */ $errors = $em->getRepository(ImportError::class);
        /** @var ProductRepository $products */ $products = $em->getRepository(Product::class);
        $decimals = new DecimalNormalizer();
        return new ImportProcessor($em, $jobs, $errors, new SpreadsheetReader(), new ProductRowMapper($decimals, new DiscountCalculator($decimals), new ImageUrlExtractor()), new ProductImportService($em, $products, new ProductRowValidator($decimals), $images, dirname(__DIR__, 2) . '/storage'), new NullLogger(), dirname(__DIR__, 2) . '/storage');
    }
}
