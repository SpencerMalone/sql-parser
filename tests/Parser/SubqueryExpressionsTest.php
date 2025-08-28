<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Tests\Parser;

use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Parsers\SubqueryExpressions;
use PhpMyAdmin\SqlParser\Tests\TestCase;

class SubqueryExpressionsTest extends TestCase
{
    /**
     * Test parsing IN subquery
     */
    public function testParseInSubquery(): void
    {
        $query = "WHERE id IN (SELECT user_id FROM orders)";
        $lexer = new Lexer($query);
        $parser = new Parser('', false);
        $parser->list = $lexer->list;
        $parser->list->idx = 2; // Skip 'WHERE id'

        $subquery = SubqueryExpressions::parse($parser, $lexer->list);

        $this->assertNotNull($subquery);
        $this->assertEquals('IN', $subquery->operator);
        $this->assertNotNull($subquery->rawSql);
        $this->assertStringContainsString('SELECT user_id FROM orders', $subquery->rawSql);
    }

    /**
     * Test parsing EXISTS subquery
     */
    public function testParseExistsSubquery(): void
    {
        $query = "WHERE EXISTS (SELECT 1 FROM orders WHERE user_id = u.id)";
        $lexer = new Lexer($query);
        $parser = new Parser('', false);
        $parser->list = $lexer->list;
        $parser->list->idx = 1; // Skip 'WHERE'

        $subquery = SubqueryExpressions::parse($parser, $lexer->list);

        $this->assertNotNull($subquery);
        $this->assertEquals('EXISTS', $subquery->operator);
        $this->assertNotNull($subquery->rawSql);
    }

    /**
     * Test parsing NOT EXISTS subquery
     */
    public function testParseNotExistsSubquery(): void
    {
        $query = "WHERE NOT EXISTS (SELECT 1 FROM orders)";
        $lexer = new Lexer($query);
        $parser = new Parser('', false);
        $parser->list = $lexer->list;
        $parser->list->idx = 1; // Skip 'WHERE'

        $subquery = SubqueryExpressions::parse($parser, $lexer->list);

        $this->assertNotNull($subquery);
        $this->assertEquals('NOT EXISTS', $subquery->operator);
    }

    /**
     * Test parsing ANY subquery
     */
    public function testParseAnySubquery(): void
    {
        $query = "WHERE price > ANY (SELECT price FROM products)";
        $lexer = new Lexer($query);
        $parser = new Parser('', false);
        $parser->list = $lexer->list;
        $parser->list->idx = 3; // Skip 'WHERE price >'

        $subquery = SubqueryExpressions::parse($parser, $lexer->list);

        $this->assertNotNull($subquery);
        $this->assertEquals('ANY', $subquery->operator);
    }

    /**
     * Test parsing ALL subquery
     */
    public function testParseAllSubquery(): void
    {
        $query = "WHERE price >= ALL (SELECT price FROM products)";
        $lexer = new Lexer($query);
        $parser = new Parser('', false);
        $parser->list = $lexer->list;
        $parser->list->idx = 3; // Skip 'WHERE price >='

        $subquery = SubqueryExpressions::parse($parser, $lexer->list);

        $this->assertNotNull($subquery);
        $this->assertEquals('ALL', $subquery->operator);
    }

    /**
     * Test parsing scalar subquery
     */
    public function testParseScalarSubquery(): void
    {
        $query = "(SELECT COUNT(*) FROM orders)";
        $lexer = new Lexer($query);
        $parser = new Parser('', false);
        $parser->list = $lexer->list;

        $subquery = SubqueryExpressions::parse($parser, $lexer->list);

        $this->assertNotNull($subquery);
        $this->assertEquals('SCALAR', $subquery->operator);
    }

    /**
     * Test parsing subquery with alias
     */
    public function testParseSubqueryWithAlias(): void
    {
        $query = "(SELECT * FROM users) AS u";
        $lexer = new Lexer($query);
        $parser = new Parser('', false);
        $parser->list = $lexer->list;

        $subquery = SubqueryExpressions::parse($parser, $lexer->list);

        $this->assertNotNull($subquery);
        $this->assertEquals('u', $subquery->alias);
    }

    /**
     * Test parsing fails when no subquery pattern is found
     */
    public function testParseReturnsNullForNonSubquery(): void
    {
        $query = "WHERE column = 'value'";
        $lexer = new Lexer($query);
        $parser = new Parser('', false);
        $parser->list = $lexer->list;
        $parser->list->idx = 1; // Skip 'WHERE'

        $subquery = SubqueryExpressions::parse($parser, $lexer->list);

        $this->assertNull($subquery);
    }
}