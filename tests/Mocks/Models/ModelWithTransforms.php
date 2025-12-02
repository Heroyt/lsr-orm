<?php
declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Transform;
use Lsr\Orm\Attributes\Transforms\Clamp;
use Lsr\Orm\Attributes\Transforms\Pad;
use Lsr\Orm\Attributes\Transforms\Trim;
use Lsr\Orm\Attributes\Transforms\Truncate;
use Lsr\Orm\Model;

#[PrimaryKey('id_model')]
class ModelWithTransforms extends Model
{

    public const string TABLE = 'model_with_transforms';

    #[Transform(save: 'transformName')]
    public string $lowercaseName = '';

    #[Transform(load: 'transformName')]
    public string $lowercaseLoadedName = '';

    #[Clamp(min: 0, max: 10)]
    public int $clampedValue = 0;

    #[Clamp(min: 0, max: 10, onLoad: false)]
    public int $clampedValueOnSave = 0;

    #[Clamp(min: 0, max: 10, onSave: false)]
    public int $clampedValueOnLoad = 0;

    #[Pad(8, '0', \STR_PAD_LEFT)]
    public string $paddedValue = '00000000';

    #[Pad(8, '0', \STR_PAD_LEFT, onLoad: false)]
    public string $paddedValueOnSave = '00000000';

    #[Pad(8, '0', \STR_PAD_LEFT, onSave: false)]
    public string $paddedValueOnLoad = '00000000';

    #[Truncate(10, '...')]
    public string $truncatedValue = '';

    #[Truncate(10, '...', onLoad: false)]
    public string $truncatedValueOnSave = '';

    #[Truncate(10, '...', onSave: false)]
    public string $truncatedValueOnLoad = '';

    #[Trim]
    public string $trimmedValue = '';

    #[Trim(onLoad: false)]
    public string $trimmedValueOnSave = '';

    #[Trim(onSave: false)]
    public string $trimmedValueOnLoad = '';

    public function transformName(string $name): string
    {
        return strtolower($name);
    }

}