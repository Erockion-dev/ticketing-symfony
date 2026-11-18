<?php

namespace App\Dto\Api\TicketComment;

final readonly class TicketCommentResponseDto
{
    public function __construct(
        public int $id,
        public string $content,
        public array $createdBy,
        public string $createdAt,
        public ?string $deletedAt,
    ) {
    }
}