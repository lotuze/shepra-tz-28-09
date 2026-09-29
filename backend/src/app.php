<?php

declare(strict_types=1);

use App\Controller\ProductController;
use App\Controller\ProductImageController;
use App\Controller\ImportController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;

$container = require __DIR__ . '/bootstrap.php';
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->get('/api/health', function (
    ServerRequestInterface $request,
    ResponseInterface $response,
): ResponseInterface {
    $response->getBody()->write(json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/api/products', [ProductController::class, 'index']);
$app->get('/api/products/{id:[0-9]+}', [ProductController::class, 'show']);
$app->get('/api/product-images/{id:[0-9]+}/content', [ProductImageController::class, 'content']);
$app->post('/api/imports', [ImportController::class, 'create']);
$app->get('/api/imports/{id:[0-9]+}', [ImportController::class, 'show']);

return $app;
