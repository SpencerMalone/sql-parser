<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Components;

use PhpMyAdmin\SqlParser\Component;
use PhpMyAdmin\SqlParser\Statement;

use function implode;
use function is_array;

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
class SubqueryExpression extends Component
{
    /**
     * The subquery statement object.
     *
     * @var Statement|null
     */
    public $statement;

    /**
     * The subquery operator (IN, EXISTS, ANY, ALL, SOME, etc.).
     *
     * @var string|null
     */
    public $operator;

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
    public $alias;

    /**
     * Raw SQL string of the subquery (fallback if statement parsing fails).
     *
     * @var string|null
     */
    public $rawSql;

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
        ?Statement $statement = null,
        ?string $operator = null,
        bool $isCorrelated = false,
        ?string $alias = null,
        ?string $rawSql = null
    ) {
        $this->statement = $statement;
        $this->operator = $operator;
        $this->isCorrelated = $isCorrelated;
        $this->alias = $alias;
        $this->rawSql = $rawSql;
    }

    /**
     * Builds the subquery expression string.
     *
     * @param mixed                $component the component to be built
     * @param array<string, mixed> $options   parameters for building
     *
     * @return string
     */
    public static function build($component, array $options = [])
    {
        if (is_array($component)) {
            $results = [];
            foreach ($component as $subComponent) {
                $results[] = static::build($subComponent, $options);
            }
            return implode(', ', $results);
        }

        if (!($component instanceof self)) {
            return '';
        }

        $result = '';

        if ($component->operator !== null && $component->operator !== 'SCALAR') {
            $result .= $component->operator . ' ';
        }

        $result .= '(';

        if ($component->statement !== null) {
            $result .= $component->statement->build();
        } elseif ($component->rawSql !== null) {
            $result .= $component->rawSql;
        }

        $result .= ')';

        if ($component->alias !== null) {
            $result .= ' AS ' . $component->alias;
        }

        return $result;
    }

    /**
     * String representation of the subquery expression.
     *
     * @return string
     */
    public function __toString()
    {
        return static::build($this);
    }
}