<?php

declare(strict_types=1);

namespace Lsr\Orm\ModelTraits;

use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Orm\Attributes\Hooks\BeforeUpdate;

trait WithUpdatedAt
{
    public ?DateTimeInterface $updatedAt = null;

    #[BeforeUpdate]
    protected function updateUpdatedAt(): void {
        $this->updatedAt = new DateTimeImmutable();
    }
}
