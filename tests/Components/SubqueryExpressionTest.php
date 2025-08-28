<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Tests\Components;

use PhpMyAdmin\SqlParser\Components\SubqueryExpression;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Tests\TestCase;

class SubqueryExpressionTest extends TestCase
{
    public function testSubqueryExpressionCreation(): void
    {
        // Test basic subquery expression creation
        $subquery = new SubqueryExpression(
            null,
            'IN',
            false,
            null,
            'SELECT id FROM users'
        );

        $this->assertEquals('IN', $subquery->operator);
        $this->assertFalse($subquery->isCorrelated);
        $this->assertEquals('SELECT id FROM users', $subquery->rawSql);
        $this->assertEquals('IN (SELECT id FROM users)', $subquery->build());
    }

    public function testSubqueryExpressionWithStatement(): void
    {
        // Create a simple SELECT statement
        $parser = new Parser('SELECT id FROM users');
        $statement = $parser->statements[0];

        $subquery = new SubqueryExpression(
            $statement,
            'EXISTS',
            true,
            null,
            null
        );

        $this->assertEquals('EXISTS', $subquery->operator);
        $this->assertTrue($subquery->isCorrelated);
        $this->assertInstanceOf(SelectStatement::class, $subquery->statement);
    }

    public function testSubqueryExpressionWithAlias(): void
    {
        $subquery = new SubqueryExpression(
            null,
            'SCALAR',
            false,
            'subq',
            'SELECT COUNT(*) FROM orders'
        );

        $this->assertEquals('subq', $subquery->alias);
        $this->assertEquals('(SELECT COUNT(*) FROM orders) AS subq', $subquery->build());
    }

    public function testSubqueryExpressionToString(): void
    {
        $subquery = new SubqueryExpression(
            null,
            'ANY',
            false,
            null,
            'SELECT price FROM products'
        );

        $this->assertEquals('ANY (SELECT price FROM products)', (string) $subquery);
    }

    public function testAllSubqueryOperators(): void
    {
        $operators = ['IN', 'NOT IN', 'EXISTS', 'NOT EXISTS', 'ANY', 'ALL', 'SOME', 'SCALAR'];
        
        foreach ($operators as $operator) {
            $subquery = new SubqueryExpression(
                null,
                $operator,
                false,
                null,
                'SELECT 1'
            );
            
            if ($operator === 'SCALAR') {
                $this->assertEquals('(SELECT 1)', $subquery->build());
            } else {
                $this->assertEquals($operator . ' (SELECT 1)', $subquery->build());
            }
        }
    }
}