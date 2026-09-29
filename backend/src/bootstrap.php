<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use App\Infrastructure\DatabaseConnectionParameters;
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
use App\Repository\ProductAttributeRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$builder = new ContainerBuilder();
$builder->addDefinitions([
    EntityManagerInterface::class => static function (): EntityManagerInterface {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__) . '/src/Entity'],
            isDevMode: ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod') !== 'prod',
        );
        $config->setSchemaAssetsFilter(static function (string|AbstractAsset $asset): bool {
            $name = $asset instanceof AbstractAsset ? $asset->getName() : $asset;
            return $name !== 'doctrine_migration_versions';
        });

        return new EntityManager(DriverManager::getConnection(DatabaseConnectionParameters::fromEnvironment(), $config), $config);
    },
    EntityManager::class => static fn (ContainerInterface $container): EntityManagerInterface =>
        $container->get(EntityManagerInterface::class),
    ProductRepository::class => static function (ContainerInterface $container): ProductRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new ProductRepository($entityManager, $entityManager->getClassMetadata(Product::class));
    },
    ProductAttributeRepository::class => static function (ContainerInterface $container): ProductAttributeRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new ProductAttributeRepository($entityManager, $entityManager->getClassMetadata(ProductAttribute::class));
    },
    ProductImageRepository::class => static function (ContainerInterface $container): ProductImageRepository {
        $entityManager = $container->get(EntityManagerInterface::class);
        return new ProductImageRepository($entityManager, $entityManager->getClassMetadata(ProductImage::class));
    },
]);

return $builder->build();
