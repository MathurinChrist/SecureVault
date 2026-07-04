<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class PasswordUpdatedEvent extends Event
{
    public const NAME = 'password.updated';

    public function __construct(
        private User $user
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }
}
