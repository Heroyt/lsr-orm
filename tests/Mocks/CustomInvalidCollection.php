<?php
declare(strict_types=1);

namespace Mocks;

class CustomInvalidCollection
{

    public function __construct(
        public array $models = [],
    ) {}

}