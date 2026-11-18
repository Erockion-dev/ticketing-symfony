<?php

namespace App\Dto\BackOffice\User;

use App\Entity\User;

final readonly class UserListItemDto
{
    public function __construct(
        public User $user,
        public int $ticketCount,
    ) {
    }
}