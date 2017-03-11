<?php
namespace Dabble;

/**
 * Literal helper class
 */
class Literal
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value;
    }
}

