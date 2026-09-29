<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Slim\App;

final class TestKernel
{
    public static ContainerInterface $container;
    public static EntityManagerInterface $entityManager;

    public static function app(): App
    {
        /** @var App $app */
        $app = require dirname(__DIR__) . '/src/app.php';
        return $app;
    }
}
