<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Parsers;

use PhpMyAdmin\SqlParser\Components\SubqueryExpression;
use PhpMyAdmin\SqlParser\Parseable;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokensList;
use PhpMyAdmin\SqlParser\TokenType;

use function in_array;
use function strtoupper;
use function trim;

/**
 * Parses subquery expressions with enhanced support for various subquery types.
 */
final class SubqueryExpressions implements Parseable
{
    /**
     * Subquery operators that indicate a subquery expression.
     */
    private const SUBQUERY_OPERATORS = [
        'IN',
        'NOT IN',
        'EXISTS',
        'NOT EXISTS',
        'ANY',
        'ALL',
        'SOME',
    ];

    /**
     * Comparison operators that can be used with ANY/ALL/SOME.
     */
    private const COMPARISON_OPERATORS = [
        '=', '!=', '<>', '<', '>', '<=', '>=',
    ];

    /**
     * Parses a subquery expression.
     *
     * @param Parser               $parser  the parser that serves as context
     * @param TokensList           $list    the list of tokens that are being parsed
     * @param array<string, mixed> $options parameters for parsing
     */
    public static function parse(Parser $parser, TokensList $list, array $options = []): SubqueryExpression|null
    {
        $ret = new SubqueryExpression();
        $operator = null;
        $parenthesesDepth = 0;
        $subqueryTokens = [];
        $foundOpenParen = false;
        $alias = null;

        // Look for subquery operators
        $startIdx = $list->idx;
        for ($i = $startIdx; $i < $list->count; $i++) {
            $token = $list->tokens[$i];

            if ($token->type === TokenType::Delimiter) {
                break;
            }

            if ($token->type === TokenType::Whitespace || $token->type === TokenType::Comment) {
                continue;
            }

            // Check for subquery operators
            if ($token->type === TokenType::Keyword) {
                $keyword = strtoupper($token->keyword);
                
                if (in_array($keyword, self::SUBQUERY_OPERATORS, true)) {
                    $operator = $keyword;
                    $list->idx = $i + 1;
                    break;
                } elseif ($keyword === 'NOT') {
                    // Look ahead for NOT IN, NOT EXISTS
                    $nextToken = null;
                    for ($j = $i + 1; $j < $list->count; $j++) {
                        if ($list->tokens[$j]->type !== TokenType::Whitespace && 
                            $list->tokens[$j]->type !== TokenType::Comment) {
                            $nextToken = $list->tokens[$j];
                            break;
                        }
                    }
                    
                    if ($nextToken && $nextToken->type === TokenType::Keyword) {
                        $nextKeyword = strtoupper($nextToken->keyword);
                        if ($nextKeyword === 'IN' || $nextKeyword === 'EXISTS') {
                            $operator = 'NOT ' . $nextKeyword;
                            $list->idx = $j + 1;
                            break;
                        }
                    }
                }
            }

            // Check for comparison operators followed by ANY/ALL/SOME
            if ($token->type === TokenType::Operator && 
                in_array($token->value, self::COMPARISON_OPERATORS, true)) {
                
                // Look ahead for ANY/ALL/SOME
                for ($j = $i + 1; $j < $list->count; $j++) {
                    $nextToken = $list->tokens[$j];
                    if ($nextToken->type === TokenType::Whitespace || 
                        $nextToken->type === TokenType::Comment) {
                        continue;
                    }
                    
                    if ($nextToken->type === TokenType::Keyword) {
                        $nextKeyword = strtoupper($nextToken->keyword);
                        if (in_array($nextKeyword, ['ANY', 'ALL', 'SOME'], true)) {
                            $operator = $nextKeyword;
                            $list->idx = $j + 1;
                            $foundOpenParen = false;
                            break 2;
                        }
                    }
                    break;
                }
            }
        }

        // If no operator found, check if we start with an opening parenthesis (scalar subquery)
        if (!$operator) {
            $currentToken = $list->tokens[$list->idx] ?? null;
            if ($currentToken && $currentToken->type === TokenType::Operator && $currentToken->value === '(') {
                $operator = 'SCALAR';
                $foundOpenParen = true;
            } else {
                return null;
            }
        }

        $ret->operator = $operator;

        // Find the opening parenthesis
        while ($list->idx < $list->count) {
            $token = $list->tokens[$list->idx];
            
            if ($token->type === TokenType::Delimiter) {
                break;
            }

            if ($token->type === TokenType::Whitespace || $token->type === TokenType::Comment) {
                ++$list->idx;
                continue;
            }

            if ($token->type === TokenType::Operator && $token->value === '(') {
                $foundOpenParen = true;
                ++$list->idx;
                break;
            }

            ++$list->idx;
        }

        if (!$foundOpenParen) {
            $parser->error('Expected opening parenthesis for subquery', $list->tokens[$list->idx] ?? null);
            return null;
        }

        // Collect tokens until we find the matching closing parenthesis
        $parenthesesDepth = 1;
        while ($list->idx < $list->count && $parenthesesDepth > 0) {
            $token = $list->tokens[$list->idx];

            if ($token->type === TokenType::Operator) {
                if ($token->value === '(') {
                    ++$parenthesesDepth;
                } elseif ($token->value === ')') {
                    --$parenthesesDepth;
                }
            }

            if ($parenthesesDepth > 0) {
                $subqueryTokens[] = $token;
            }

            ++$list->idx;
        }

        if ($parenthesesDepth > 0) {
            $parser->error('Unmatched opening parenthesis in subquery', null);
            return null;
        }

        // Check for alias after closing parenthesis (for derived tables)
        while ($list->idx < $list->count) {
            $token = $list->tokens[$list->idx];
            
            if ($token->type === TokenType::Delimiter) {
                break;
            }

            if ($token->type === TokenType::Whitespace || $token->type === TokenType::Comment) {
                ++$list->idx;
                continue;
            }

            if ($token->type === TokenType::Keyword && strtoupper($token->keyword) === 'AS') {
                ++$list->idx;
                // Skip whitespace
                while ($list->idx < $list->count && 
                       ($list->tokens[$list->idx]->type === TokenType::Whitespace || 
                        $list->tokens[$list->idx]->type === TokenType::Comment)) {
                    ++$list->idx;
                }
                
                // Get alias
                if ($list->idx < $list->count) {
                    $alias = $list->tokens[$list->idx]->value;
                    ++$list->idx;
                }
                break;
            } elseif ($token->type === TokenType::None || $token->type === TokenType::Symbol) {
                // Alias without AS keyword
                $alias = $token->value;
                ++$list->idx;
                break;
            } else {
                break;
            }
        }

        $ret->alias = $alias;

        // Try to parse the subquery as a statement
        if (!empty($subqueryTokens)) {
            $subqueryTokensList = new TokensList($subqueryTokens);
            $subqueryParser = new Parser('', false);
            $subqueryParser->list = $subqueryTokensList;
            
            try {
                $subqueryParser->parseQuery();
                if (!empty($subqueryParser->statements)) {
                    $ret->statement = $subqueryParser->statements[0];
                }
            } catch (\Exception $e) {
                // If parsing fails, store as raw SQL
                $rawSql = '';
                foreach ($subqueryTokens as $token) {
                    $rawSql .= $token->token;
                }
                $ret->rawSql = trim($rawSql);
            }
            
            // Copy any errors from subquery parser
            $parser->errors = array_merge($parser->errors, $subqueryParser->errors);
        }

        return $ret;
    }
}