<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Hooks\AfterExternalUpdate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('model_e_id')]
class ModelE extends Model
{
    public const string TABLE = 'modelsE';

    public string $name;

    /** @var ModelCollection<ModelD> */
    #[ManyToMany('modelsD_modelsE', class: ModelD::class, loadingType: LoadingType::EAGER)]
    public ModelCollection $models;

    /** @var int Counter for hook calls */
    public static int $hookCallCount = 0;

    /** @var int|null Last model ID that triggered the hook */
    public static ?int $lastHookId = null;

    /**
     * Hook method called after this model is externally updated
     *
     * @param  int  $id  Model ID that was affected
     */
    #[AfterExternalUpdate]
    public static function onExternalUpdate(int $id) : void {
        self::$hookCallCount++;
        self::$lastHookId = $id;
    }
}
