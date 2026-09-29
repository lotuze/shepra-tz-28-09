<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class UserFixtures implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $email = 'admin@example.com';
        $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User($email, 'ChangeMe123!');
            $manager->persist($user);
        } else {
            $user->setPassword('ChangeMe123!');
        }
        $manager->flush();
    }
}
