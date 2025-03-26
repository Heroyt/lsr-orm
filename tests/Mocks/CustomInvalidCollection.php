<?php
declare(strict_types=1);

namespace Mocks;

class CustomInvalidCollection
{

    /**
     * @param  mixed[]  $models
     */
    public function __construct(
        public array $models = [],
    ) {}

}