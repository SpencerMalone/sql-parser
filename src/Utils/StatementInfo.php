<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Utils;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statement;

final class StatementInfo
{
    /**
     * @var Parser The parser used to analyze the statement.
     */
    public $parser;

    /**
     * @var Statement|null The first statement resulted from parsing.
     */
    public $statement;

    /**
     * @var StatementFlags
     */
    public $flags;

    /**
     * @var array[] The real name of the tables selected; if there are no table names in the
     *              `SELECT` expressions, the table names are fetched from the `FROM` expressions
     * @psalm-var list<array{string|null, string|null}>
     */
    public $selectTables;

    /**
     * @var array
     * @psalm-var list<string|null>
     */
    public $selectExpressions;

    /**
     * @param Parser         $parser           The parser used to analyze the statement.
     * @param Statement|null $statement        The first statement resulted from parsing.
     * @param StatementFlags $flags
     * @param array[]        $selectTables     The real name of the tables selected; if there are no table names in the
     *                                         `SELECT` expressions, the table names are fetched from the `FROM` expressions
     * @param array          $selectExpressions
     * @psalm-param list<array{string|null, string|null}> $selectTables
     * @psalm-param list<string|null> $selectExpressions
     */
    public function __construct(
        Parser $parser,
        ?Statement $statement,
        StatementFlags $flags,
        array $selectTables,
        array $selectExpressions
    ) {
        $this->parser = $parser;
        $this->statement = $statement;
        $this->flags = $flags;
        $this->selectTables = $selectTables;
        $this->selectExpressions = $selectExpressions;
    }
}
