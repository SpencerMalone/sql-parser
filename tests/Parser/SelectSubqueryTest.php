<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Tests\Parser;

use PhpMyAdmin\SqlParser\Tests\TestCase;

class SelectSubqueryTest extends TestCase
{
    /**
     * Test parsing SELECT with IN subquery
     */
    public function testParseSelectWithInSubquery(): void
    {
        $query = "SELECT * FROM users WHERE id IN (SELECT user_id FROM orders WHERE status = 'completed')";
        
        $parser = $this->getParser($query);
        
        $this->assertEquals(0, count($parser->errors));
        $this->assertEquals(1, count($parser->statements));
        
        $statement = $parser->statements[0];
        $this->assertInstanceOf('PhpMyAdmin\\SqlParser\\Statements\\SelectStatement', $statement);
        
        // Verify that the WHERE clause contains the subquery
        $this->assertNotEmpty($statement->where);
        
        // The subquery should be detected somewhere in the structure
        $foundSubquery = false;
        foreach ($statement->where as $condition) {
            if (strpos($condition->expr, 'SELECT') !== false) {
                $foundSubquery = true;
                break;
            }
        }
        $this->assertTrue($foundSubquery, 'Subquery should be detected in WHERE clause');
    }

    /**
     * Test parsing SELECT with EXISTS subquery
     */
    public function testParseSelectWithExistsSubquery(): void
    {
        $query = "SELECT * FROM customers c WHERE EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.id)";
        
        $parser = $this->getParser($query);
        
        $this->assertEquals(0, count($parser->errors));
        $this->assertEquals(1, count($parser->statements));
    }

    /**
     * Test parsing SELECT with scalar subquery in SELECT clause
     */
    public function testParseSelectWithScalarSubquery(): void
    {
        $query = "SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count FROM users u";
        
        $parser = $this->getParser($query);
        
        $this->assertEquals(0, count($parser->errors));
        $this->assertEquals(1, count($parser->statements));
        
        $statement = $parser->statements[0];
        
        // Check if expressions contain subqueries
        $foundSubquery = false;
        if (!empty($statement->expr)) {
            foreach ($statement->expr as $expr) {
                if ($expr->subquery) {
                    $foundSubquery = true;
                    break;
                }
            }
        }
        $this->assertTrue($foundSubquery, 'Subquery should be detected in SELECT clause');
    }

    /**
     * Test parsing SELECT with derived table (subquery in FROM)
     */
    public function testParseSelectWithDerivedTable(): void
    {
        $query = "SELECT * FROM (SELECT * FROM users WHERE active = 1) AS active_users WHERE created_at > '2023-01-01'";
        
        $parser = $this->getParser($query);
        
        $this->assertEquals(0, count($parser->errors));
        $this->assertEquals(1, count($parser->statements));
        
        $statement = $parser->statements[0];
        
        // Check if FROM clause contains subqueries
        $foundSubquery = false;
        if (!empty($statement->from)) {
            foreach ($statement->from as $from) {
                if ($from->subquery) {
                    $foundSubquery = true;
                    break;
                }
            }
        }
        $this->assertTrue($foundSubquery, 'Subquery should be detected in FROM clause');
    }

    /**
     * Test parsing SELECT with nested subqueries
     */
    public function testParseSelectWithNestedSubqueries(): void
    {
        $query = "SELECT * FROM users WHERE id IN (SELECT user_id FROM orders WHERE product_id IN (SELECT id FROM products WHERE category = 'premium'))";
        
        $parser = $this->getParser($query);
        
        $this->assertEquals(0, count($parser->errors));
        $this->assertEquals(1, count($parser->statements));
    }

    /**
     * Test parsing SELECT with ANY/ALL subqueries
     */
    public function testParseSelectWithAnyAllSubqueries(): void
    {
        $queries = [
            "SELECT * FROM products WHERE price > ANY (SELECT price FROM products WHERE category = 'electronics')",
            "SELECT * FROM products WHERE price >= ALL (SELECT price FROM products WHERE category = 'budget')",
        ];
        
        foreach ($queries as $query) {
            $parser = $this->getParser($query);
            
            $this->assertEquals(0, count($parser->errors), "Query should parse without errors: $query");
            $this->assertEquals(1, count($parser->statements));
        }
    }

    /**
     * Test parsing SELECT with correlated subquery
     */
    public function testParseSelectWithCorrelatedSubquery(): void
    {
        $query = "SELECT * FROM orders o WHERE o.total > (SELECT AVG(total) FROM orders WHERE customer_id = o.customer_id)";
        
        $parser = $this->getParser($query);
        
        $this->assertEquals(0, count($parser->errors));
        $this->assertEquals(1, count($parser->statements));
    }

    /**
     * Test query rebuilding with subqueries
     */
    public function testSubqueryQueryRebuilding(): void
    {
        $queries = [
            "SELECT * FROM users WHERE id IN (SELECT user_id FROM orders)",
            "SELECT * FROM customers WHERE EXISTS (SELECT 1 FROM orders WHERE customer_id = customers.id)",
            "SELECT (SELECT COUNT(*) FROM orders) AS total_orders FROM dual",
        ];
        
        foreach ($queries as $originalQuery) {
            $parser = $this->getParser($originalQuery);
            
            $this->assertEquals(0, count($parser->errors), "Query should parse without errors: $originalQuery");
            
            if (!empty($parser->statements)) {
                $statement = $parser->statements[0];
                $rebuiltQuery = $statement->build();
                
                // The rebuilt query should contain the subquery
                $this->assertStringContainsString('SELECT', $rebuiltQuery);
                $this->assertStringContainsString('(', $rebuiltQuery);
                $this->assertStringContainsString(')', $rebuiltQuery);
            }
        }
    }
}