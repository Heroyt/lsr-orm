<?php

declare(strict_types=1);

namespace Lsr\Orm\ModelTraits;

use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;

trait WithCreatedAt
{
    public DateTimeInterface $createdAt;

    #[BeforeInsert]
    protected function updateCreatedAt(): void {
        if (!isset($this->createdAt)) {
            $this->createdAt = new DateTimeImmutable();
        }
    }
}
