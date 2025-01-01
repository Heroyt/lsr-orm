<?php

declare(strict_types=1);

namespace Mocks\Models;

use Dibi\Row;
use Lsr\Orm\Interfaces\InsertExtendInterface;

final class SimpleData implements InsertExtendInterface
{
    public function __construct(
        public string $value1,
        public string $value2,
    ) {
    }


    public static function parseRow(Row $row): static {
        assert(is_string($row->value1));
        assert(is_string($row->value2));
        return new SimpleData(
            $row->value1,
            $row->value2,
        );
    }

    /**
     * Add data from the object into the data array for DB INSERT/UPDATE
     *
     * @param  array<string, mixed>  $data
     */
    public function addQueryData(array &$data): void {
        $data['value1'] = $this->value1;
        $data['value2'] = $this->value2;
    }
}
