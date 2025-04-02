<?php
declare(strict_types=1);

namespace Mocks\Models;

use DateTimeInterface;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\Hooks\BeforeUpdate;

trait WithHooks
{

    public static int $cacheCleared = 0;
    public ?DateTimeInterface $updatedAt = null;

    #[BeforeUpdate]
    public function setUpdatedAt() : void {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[AfterUpdate, AfterInsert, AfterDelete]
    public function clearCache() : void {
        static::$cacheCleared++;
    }

}