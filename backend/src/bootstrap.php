<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use App\Infrastructure\DatabaseConnectionParameters;
use App\Controller\ProductImageController;
use App\Controller\AuthController;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Entity\User;
use App\Repository\ProductAttributeRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Security\JwtService;
use App\Security\ImportRateLimiter;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\ImportRateLimitMiddleware;
use App\Entity\ImportError;
use App\Entity\ImportJob;
use App\Import\ImageDownloader;
use App\Import\ImageDownloaderInterface;
use App\Import\ImportProcessor;
use App\Import\ImportSubmissionService;
use App\Import\ProductImportService;
use App\Message\ImportProductsMessage;
use App\MessageHandler\ImportProductsMessageHandler;
use App\Repository\ImportErrorRepository;
use App\Repository\ImportJobRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

$root = dirname(__DIR__);
$proxyDir = $root . '/storage/cache/doctrine-proxies';
if (!is_dir($proxyDir) && !mkdir($proxyDir, 0775, true) && !is_dir($proxyDir)) {
    throw new RuntimeException('Unable to create Doctrine proxy directory.');
}

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$builder = new ContainerBuilder();
$builder->addDefinitions([
    EntityManagerInterface::class => static function () use ($proxyDir): EntityManagerInterface {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__) . '/src/Entity'],
            isDevMode: ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod') !== 'prod',
            proxyDir: $proxyDir,
        );
        $config->setSchemaAssetsFilter(static function (string|AbstractAsset $asset): bool {
            $name = $asset instanceof AbstractAsset ? $asset->getName() : $asset;
            return !in_array($name, ['doctrine_migration_versions', 'import_rate_limits'], true);
        });

        return new EntityManager(DriverManager::getConnection(DatabaseConnectionParameters::fromEnvironment(), $config), $config);
    },
    EntityManager::class => static fn (ContainerInterface $container): EntityManagerInterface =>
        $container->get(EntityManagerInterface::class),
    ProductRepository::class => static function (ContainerInterface $container): ProductRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new ProductRepository($entityManager, $entityManager->getClassMetadata(Product::class));
    },
    UserRepository::class => static function (ContainerInterface $container): UserRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new UserRepository($entityManager, $entityManager->getClassMetadata(User::class));
    },
    JwtService::class => static function (): JwtService {
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
        if (!is_string($secret) || $secret === '') { throw new RuntimeException('JWT_SECRET is required.'); }
        return new JwtService($secret, (int) ($_ENV['JWT_TTL'] ?? getenv('JWT_TTL') ?: 3600));
    },
    AuthController::class => static fn (ContainerInterface $container): AuthController => new AuthController(
        $container->get(UserRepository::class),
        $container->get(JwtService::class),
    ),
    JwtAuthMiddleware::class => static fn (ContainerInterface $container): JwtAuthMiddleware => new JwtAuthMiddleware($container->get(JwtService::class)),
    ImportRateLimiter::class => static fn (ContainerInterface $container): ImportRateLimiter => new ImportRateLimiter(
        $container->get(EntityManagerInterface::class)->getConnection(),
        (int) ($_ENV['IMPORT_RATE_LIMIT'] ?? getenv('IMPORT_RATE_LIMIT') ?: 5),
        (int) ($_ENV['IMPORT_RATE_WINDOW_SECONDS'] ?? getenv('IMPORT_RATE_WINDOW_SECONDS') ?: 60),
    ),
    ImportRateLimitMiddleware::class => static fn (ContainerInterface $container): ImportRateLimitMiddleware => new ImportRateLimitMiddleware($container->get(ImportRateLimiter::class)),
    ProductAttributeRepository::class => static function (ContainerInterface $container): ProductAttributeRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new ProductAttributeRepository($entityManager, $entityManager->getClassMetadata(ProductAttribute::class));
    },
    ProductImageRepository::class => static function (ContainerInterface $container): ProductImageRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new ProductImageRepository($entityManager, $entityManager->getClassMetadata(ProductImage::class));
    },
    ProductImageController::class => static fn (ContainerInterface $container): ProductImageController => new ProductImageController(
        $container->get(ProductImageRepository::class),
        dirname(__DIR__) . '/storage',
    ),
    ImportJobRepository::class => static function (ContainerInterface $container): ImportJobRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new ImportJobRepository($entityManager, $entityManager->getClassMetadata(ImportJob::class));
    },
    ImportErrorRepository::class => static function (ContainerInterface $container): ImportErrorRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new ImportErrorRepository($entityManager, $entityManager->getClassMetadata(ImportError::class));
    },
    LoggerInterface::class => static function (): LoggerInterface {
        $logger = new Logger('app');
        $logger->pushHandler(new StreamHandler('php://stdout'));
        return $logger;
    },
    HttpClientInterface::class => static fn (): HttpClientInterface => HttpClient::create(),
    ImageDownloaderInterface::class => static fn (ContainerInterface $container): ImageDownloaderInterface => new ImageDownloader(
        $container->get(HttpClientInterface::class),
        dirname(__DIR__) . '/storage',
        (int) ($_ENV['IMAGE_DOWNLOAD_MAX_SIZE'] ?? getenv('IMAGE_DOWNLOAD_MAX_SIZE') ?: 10_485_760),
        (float) ($_ENV['IMAGE_DOWNLOAD_TIMEOUT'] ?? getenv('IMAGE_DOWNLOAD_TIMEOUT') ?: 10),
    ),
    ProductImportService::class => static fn (ContainerInterface $container): ProductImportService => new ProductImportService(
        $container->get(EntityManagerInterface::class),
        $container->get(ProductRepository::class),
        $container->get(\App\Import\ProductRowValidator::class),
        $container->get(ImageDownloaderInterface::class),
        dirname(__DIR__) . '/storage',
    ),
    ImportProcessor::class => static fn (ContainerInterface $container): ImportProcessor => new ImportProcessor(
        $container->get(EntityManagerInterface::class),
        $container->get(ImportJobRepository::class),
        $container->get(ImportErrorRepository::class),
        $container->get(\App\Import\SpreadsheetReader::class),
        $container->get(\App\Import\ProductRowMapper::class),
        $container->get(ProductImportService::class),
        $container->get(LoggerInterface::class),
        dirname(__DIR__) . '/storage',
    ),
    TransportInterface::class => static function (): TransportInterface {
        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'test') { return new InMemoryTransport(); }
        $dsn = $_ENV['MESSENGER_TRANSPORT_DSN'] ?? getenv('MESSENGER_TRANSPORT_DSN');
        if (!is_string($dsn) || $dsn === '') { throw new RuntimeException('MESSENGER_TRANSPORT_DSN is required.'); }
        return (new TransportFactory([new AmqpTransportFactory()]))->createTransport($dsn, [
            'read_timeout' => 1,
            'exchange' => ['name' => 'product_import', 'type' => 'direct', 'default_publish_routing_key' => 'product_import'],
            'queues' => ['async' => ['binding_keys' => ['product_import']]],
        ], new PhpSerializer());
    },
    MessageBusInterface::class => static function (ContainerInterface $container): MessageBusInterface {
        $transport = $container->get(TransportInterface::class);
        $senderContainer = new class ($transport) implements ContainerInterface {
            public function __construct(private readonly TransportInterface $transport) {}
            public function get(string $id): mixed
            {
                if ($id !== 'async') { throw new \RuntimeException('Unknown Messenger sender: ' . $id); }
                return $this->transport;
            }
            public function has(string $id): bool { return $id === 'async'; }
        };
        $senders = new SendersLocator([ImportProductsMessage::class => ['async']], $senderContainer);
        $handler = new ImportProductsMessageHandler($container->get(ImportProcessor::class), $container->get(LoggerInterface::class));
        return new MessageBus([
            new SendMessageMiddleware($senders),
            new HandleMessageMiddleware(new HandlersLocator([ImportProductsMessage::class => [new HandlerDescriptor($handler)]])),
        ]);
    },
    EventDispatcherInterface::class => static function (ContainerInterface $container): EventDispatcherInterface {
        $transport = $container->get(TransportInterface::class);
        $senders = new class ($transport) implements ContainerInterface {
            public function __construct(private readonly TransportInterface $transport) {}
            public function get(string $id): mixed { return $this->transport; }
            public function has(string $id): bool { return $id === 'async'; }
        };
        $strategies = new class implements ContainerInterface {
            public function get(string $id): mixed { return new MultiplierRetryStrategy(3, 1000, 2, 10000, 0.1); }
            public function has(string $id): bool { return $id === 'async'; }
        };
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new DispatchPcntlSignalListener());
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($senders, $strategies, $container->get(LoggerInterface::class), $dispatcher));
        return $dispatcher;
    },
    ImportSubmissionService::class => static fn (ContainerInterface $container): ImportSubmissionService => new ImportSubmissionService(
        $container->get(EntityManagerInterface::class),
        $container->get(MessageBusInterface::class),
        dirname(__DIR__) . '/storage',
        (int) ($_ENV['IMPORT_MAX_FILE_SIZE'] ?? getenv('IMPORT_MAX_FILE_SIZE') ?: 10_485_760),
    ),
]);

return $builder->build();
