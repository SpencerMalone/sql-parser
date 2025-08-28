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
     */
    public Statement|null $statement = null;

    /**
     * The subquery operator (IN, EXISTS, ANY, ALL, SOME, etc.).
     */
    public string|null $operator = null;

    /**
     * Whether this is a correlated subquery (references outer query).
     */
    public bool $isCorrelated = false;

    /**
     * The alias for the subquery (mainly for derived tables).
     */
    public string|null $alias = null;

    /**
     * Raw SQL string of the subquery (fallback if statement parsing fails).
     */
    public string|null $rawSql = null;

    public function __construct(
        Statement|null $statement = null,
        string|null $operator = null,
        bool $isCorrelated = false,
        string|null $alias = null,
        string|null $rawSql = null,
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

        if ($this->operator && $this->operator !== 'SCALAR') {
            $result .= $this->operator . ' ';
        }

        $result .= '(';

        if ($this->statement) {
            $result .= $this->statement->build();
        } elseif ($this->rawSql) {
            $result .= $this->rawSql;
        }

        $result .= ')';

        if ($this->alias) {
            $result .= ' AS ' . $this->alias;
        }

        return $result;
    }

    public function __toString(): string
    {
        return $this->build();
    }
}