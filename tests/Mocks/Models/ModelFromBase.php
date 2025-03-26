<?php
declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;

#[PrimaryKey('id_model_from_base')]
class ModelFromBase extends BaseModel
{

    public const string TABLE = 'model_from_base';

    public string $name;

    #[NoDB]
    public string $noDB;

    public string $virtual {
        get => 'hello';
    }

    /** @var string[] */
    #[NoDB]
    public array $virtualNoDb {
        get => ['hello', 'world'];
    }

}