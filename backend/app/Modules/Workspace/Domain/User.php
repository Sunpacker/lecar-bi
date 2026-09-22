<?php

namespace App\Modules\Workspace\Domain;

final class User
{
    public function __construct(
        private readonly UserId $id,
        private string $email,
        private string $name,
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
}
