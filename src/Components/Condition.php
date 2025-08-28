<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Components;

use PhpMyAdmin\SqlParser\Component;

use function trim;

final class Condition implements Component
{
    /**
     * Identifiers recognized.
     *
     * @var array<int, mixed>
     */
    public array $identifiers = [];

    /**
     * Whether this component is an operator.
     */
    public bool $isOperator = false;

    /**
     * The condition.
     */
    public string $expr;

    public string $leftOperand = '';
    public string $operator = '';
    public string $rightOperand = '';

    /**
     * Parsed components of the condition (expressions, subqueries, etc.).
     *
     * @var Component[]|null
     */
    public $components = null;

    /** @param string|null $expr the condition or the operator */
    public function __construct($expr = null)
    {
        $this->expr = trim((string) $expr);
    }

    public function build(): string
    {
        if ($this->components !== null) {
            $result = '';
            foreach ($this->components as $component) {
                $result .= $component->build();
            }
            return $result;
        }
        
        return $this->expr;
    }

    public function __toString(): string
    {
        return $this->build();
    }
}
