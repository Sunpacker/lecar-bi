<?php

namespace App\Modules\Workspace\Domain;

final class User
{
    public function __construct(
        private readonly UserId $id,
        private string $email,
        private string $name,
        private string $passwordHash = '$2y$10$buTvr4lk7X6FIR53.u5J4u6V5mZ9ybiGa83pzR316cMEA7NoB9q0K',
    ) {}

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        if ($this->passwordHash === '') {
            return false;
        }

        return password_verify($plainPassword, $this->passwordHash);
    }

    public function changeName(string $name): void
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('User name cannot be empty.');
        }

        $this->name = $trimmed;
    }

    public function changePasswordHash(string $newPasswordHash): void
    {
        if ($newPasswordHash === '') {
            throw new \InvalidArgumentException('Password hash cannot be empty.');
        }

        $this->passwordHash = $newPasswordHash;
    }
}
