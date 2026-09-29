<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Security\JwtService;
use App\Tests\TestKernel;
use Psr\Http\Message\ServerRequestInterface;

trait AuthenticationTrait
{
    private function authorized(ServerRequestInterface $request): ServerRequestInterface
    {
        $user = TestKernel::$entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertInstanceOf(User::class, $user);
        $token = TestKernel::$container->get(JwtService::class)->issue($user)['token'];

        return $request->withHeader('Authorization', 'Bearer ' . $token);
    }
}
