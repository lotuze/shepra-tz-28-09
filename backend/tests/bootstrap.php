<?php

declare(strict_types=1);

use App\DataFixtures\ProductFixtures;
use App\DataFixtures\UserFixtures;
use App\Infrastructure\DatabaseConnectionParameters;
use App\Tests\TestKernel;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

require dirname(__DIR__) . '/vendor/autoload.php';

$developmentUrl = getenv('DATABASE_URL');
if (!is_string($developmentUrl) || $developmentUrl === '') {
    throw new RuntimeException('DATABASE_URL is required for tests.');
}

$developmentParts = parse_url($developmentUrl);
$developmentDatabase = ltrim((string) ($developmentParts['path'] ?? ''), '/');
$testDatabase = $developmentDatabase . '_test';

if (preg_match('/^[a-zA-Z0-9_]+$/', $testDatabase) !== 1) {
    throw new RuntimeException('Unsafe test database name.');
}

$admin = new PDO(
    sprintf('pgsql:host=%s;port=%d;dbname=postgres', $developmentParts['host'], $developmentParts['port'] ?? 5432),
    rawurldecode((string) $developmentParts['user']),
    rawurldecode((string) ($developmentParts['pass'] ?? '')),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$exists = $admin->prepare('SELECT 1 FROM pg_database WHERE datname = :name');
$exists->execute(['name' => $testDatabase]);
if ($exists->fetchColumn() === false) {
    $admin->exec(sprintf('CREATE DATABASE "%s"', $testDatabase));
}

$testUrl = preg_replace('#/[^/?]+(?=\?|$)#', '/' . $testDatabase, $developmentUrl);
putenv('DATABASE_URL=' . $testUrl);
putenv('APP_ENV=test');
putenv('JWT_SECRET=test-only-jwt-secret-with-at-least-32-characters');
putenv('JWT_TTL=3600');
putenv('IMPORT_RATE_LIMIT=5');
putenv('IMPORT_RATE_WINDOW_SECONDS=60');
$_ENV['DATABASE_URL'] = $testUrl;
$_ENV['APP_ENV'] = 'test';
$_ENV['JWT_SECRET'] = 'test-only-jwt-secret-with-at-least-32-characters';
$_ENV['JWT_TTL'] = '3600';
$_ENV['IMPORT_RATE_LIMIT'] = '5';
$_ENV['IMPORT_RATE_WINDOW_SECONDS'] = '60';

TestKernel::$container = require dirname(__DIR__) . '/src/bootstrap.php';
TestKernel::$entityManager = TestKernel::$container->get(EntityManagerInterface::class);

$schemaTool = new SchemaTool(TestKernel::$entityManager);
$metadata = TestKernel::$entityManager->getMetadataFactory()->getAllMetadata();
TestKernel::$entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS import_rate_limits');
$schemaTool->dropDatabase();
$schemaTool->createSchema($metadata);
TestKernel::$entityManager->getConnection()->executeStatement('CREATE TABLE import_rate_limits (user_id INT NOT NULL PRIMARY KEY, window_started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, request_count INT NOT NULL, CONSTRAINT fk_import_rate_limits_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE)');

$executor = new ORMExecutor(TestKernel::$entityManager, new ORMPurger(TestKernel::$entityManager));
$executor->execute([new ProductFixtures(), new UserFixtures()]);
TestKernel::$entityManager->clear();
