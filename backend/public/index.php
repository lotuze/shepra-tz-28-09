<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = require dirname(__DIR__) . '/src/bootstrap.php';
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->get('/api/health', function (
    ServerRequestInterface $request,
    ResponseInterface $response
): ResponseInterface {
    $response->getBody()->write(json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
