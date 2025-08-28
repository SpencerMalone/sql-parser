<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Components;

use PhpMyAdmin\SqlParser\Component;
use PhpMyAdmin\SqlParser\Statement;

/**
 * Represents a subquery within an expression.
 *
 * This component handles subqueries that appear in various contexts:
 * - SELECT clause: SELECT (subquery) AS alias
 * - WHERE clause: WHERE column IN (subquery)
 * - FROM clause: FROM (subquery) AS alias
 * - EXISTS expressions: WHERE EXISTS (subquery)
 * - ANY/ALL/SOME expressions: WHERE column > ANY (subquery)
 */
final class SubqueryExpression implements Component
{
    /**
     * The subquery statement object.
     *
     * @var Statement|null
     */
    public $statement = null;

    /**
     * The subquery operator (IN, EXISTS, ANY, ALL, SOME, etc.).
     *
     * @var string|null
     */
    public $operator = null;

    /**
     * Whether this is a correlated subquery (references outer query).
     *
     * @var bool
     */
    public $isCorrelated = false;

    /**
     * The alias for the subquery (mainly for derived tables).
     *
     * @var string|null
     */
    public $alias = null;

    /**
     * Raw SQL string of the subquery (fallback if statement parsing fails).
     *
     * @var string|null
     */
    public $rawSql = null;

    /**
     * SubqueryExpression constructor.
     *
     * @param Statement|null $statement    The subquery statement object
     * @param string|null    $operator     The subquery operator
     * @param bool           $isCorrelated Whether this is a correlated subquery
     * @param string|null    $alias        The alias for the subquery
     * @param string|null    $rawSql       Raw SQL string of the subquery
     */
    public function __construct(
        $statement = null,
        $operator = null,
        $isCorrelated = false,
        $alias = null,
        $rawSql = null
    ) {
        $this->statement = $statement;
        $this->operator = $operator;
        $this->isCorrelated = $isCorrelated;
        $this->alias = $alias;
        $this->rawSql = $rawSql;
    }

    public function build(): string
    {
        $result = '';

        if ($this->operator !== null && $this->operator !== 'SCALAR') {
            $result .= $this->operator . ' ';
        }

        $result .= '(';

        if ($this->statement !== null) {
            $result .= $this->statement->build();
        } elseif ($this->rawSql !== null) {
            $result .= $this->rawSql;
        }

        $result .= ')';

        if ($this->alias !== null) {
            $result .= ' AS ' . $this->alias;
        }

        return $result;
    }

    public function __toString(): string
    {
        return $this->build();
    }
}