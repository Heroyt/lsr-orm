<?php
declare(strict_types=1);

namespace Lsr\Orm\Traits;

trait WithArrayAccess
{

    /**
     * @inheritdoc
     */
    public function offsetGet($offset) : mixed {
        if ($this->offsetExists($offset)) {
            return $this->$offset;
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset) : bool {
        return property_exists($this, $offset);
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value) : void {
        if (isset($offset) && $this->offsetExists($offset)) {
            $this->$offset = $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset) : void {
        // Do nothing
    }

}