<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Components;

use PhpMyAdmin\SqlParser\Component;

/**
 * `ORDER BY` keyword parser.
 */
final class OrderKeyword implements Component
{
    /**
     * The expression that is used for ordering.
     *
     * @var Expression|null
     */
    public $expr = null;

    /**
     * The order type.
     *
     * @var string
     */
    public $type;

    /**
     * @param Expression|null $expr the expression that we are sorting by
     * @param string          $type the sorting type
     */
    public function __construct($expr = null, $type = OrderSortKeyword::Asc)
    {
        $this->expr = $expr;
        $this->type = $type;
    }

    public function build(): string
    {
        return $this->expr . ' ' . $this->type;
    }

    public function __toString(): string
    {
        return $this->build();
    }
}
