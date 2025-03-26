<?php
declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\Hooks\BeforeDelete;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;
use Lsr\Orm\Attributes\Hooks\BeforeUpdate;
use Lsr\Orm\Model;

abstract class BaseModel extends Model
{
    public static int $beforeUpdateCounter = 0;
    public static int $afterUpdateCounter = 0;
    public static int $beforeInsertCounter = 0;
    public static int $afterInsertCounter = 0;
    public static int $beforeDeleteCounter = 0;
    public static int $afterDeleteCounter = 0;

    #[BeforeUpdate]
    public function doSomethingBeforeUpdate() : void {
        self::$beforeUpdateCounter++;
    }

    #[AfterUpdate]
    public function doSomethingAfterUpdate() : void {
        self::$afterUpdateCounter++;
    }

    #[BeforeInsert]
    public function doSomethingBeforeInsert() : void {
        self::$beforeInsertCounter++;
    }

    #[AfterInsert]
    public function doSomethingAfterInsert() : void {
        self::$afterInsertCounter++;
    }

    #[BeforeDelete]
    public function doSomethingBeforeDelete() : void {
        self::$beforeDeleteCounter++;
    }

    #[AfterDelete]
    public function doSomethingAfterDelete() : void {
        self::$afterDeleteCounter++;
    }

}