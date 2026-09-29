<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
final class User
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $email, string $plainPassword)
    {
        $this->email = self::normalizeEmail($email);
        $this->setPassword($plainPassword);
        $this->createdAt = new DateTimeImmutable();
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }

    public function setPassword(string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash password.');
        }
        $this->passwordHash = $hash;
    }
}
