<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Parsers;

use PhpMyAdmin\SqlParser\Components\SubqueryExpression;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokensList;

use function in_array;
use function is_string;
use function strtoupper;
use function trim;

/**
 * Parses subquery expressions with enhanced support for various subquery types.
 */
class SubqueryExpressions
{
    /**
     * Subquery operators that indicate a subquery expression.
     *
     * @var string[]
     */
    private static $subqueryOperators = [
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
     *
     * @var string[]
     */
    private static $comparisonOperators = [
        '=', '!=', '<>', '<', '>', '<=', '>=',
    ];

    /**
     * Parses a subquery expression.
     *
     * @param Parser                $parser  the parser that serves as context
     * @param TokensList            $list    the list of tokens that are being parsed
     * @param array<string, mixed>  $options parameters for parsing
     *
     * @return SubqueryExpression|null
     */
    public static function parse(Parser $parser, TokensList $list, array $options = [])
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

            if ($token->type === Token::TYPE_DELIMITER) {
                break;
            }

            if ($token->type === Token::TYPE_WHITESPACE || $token->type === Token::TYPE_COMMENT) {
                continue;
            }

            // Check for subquery operators
            if ($token->type === Token::TYPE_KEYWORD && is_string($token->keyword)) {
                $keyword = strtoupper($token->keyword);
                
                if (in_array($keyword, self::$subqueryOperators, true)) {
                    $operator = $keyword;
                    $list->idx = $i + 1;
                    break;
                } elseif ($keyword === 'NOT') {
                    // Look ahead for NOT IN, NOT EXISTS
                    $nextToken = null;
                    $jj = $i;
                    for (; $jj < $list->count; $jj++) {
                        if ($jj <= $i) {
                            continue;
                        }
                        if ($list->tokens[$jj]->type !== Token::TYPE_WHITESPACE && 
                            $list->tokens[$jj]->type !== Token::TYPE_COMMENT) {
                            $nextToken = $list->tokens[$jj];
                            break;
                        }
                    }
                    
                    if ($nextToken !== null && $nextToken->type === Token::TYPE_KEYWORD && is_string($nextToken->keyword)) {
                        $nextKeyword = strtoupper($nextToken->keyword);
                        if ($nextKeyword === 'IN' || $nextKeyword === 'EXISTS') {
                            $operator = 'NOT ' . $nextKeyword;
                            $list->idx = $jj + 1;
                            break;
                        }
                    }
                }
            }

            // Check for comparison operators followed by ANY/ALL/SOME
            if ($token->type === Token::TYPE_OPERATOR && 
                in_array($token->value, self::$comparisonOperators, true)) {
                
                // Look ahead for ANY/ALL/SOME
                for ($j = $i + 1; $j < $list->count; $j++) {
                    $nextToken = $list->tokens[$j];
                    if ($nextToken->type === Token::TYPE_WHITESPACE || 
                        $nextToken->type === Token::TYPE_COMMENT) {
                        continue;
                    }
                    
                    if ($nextToken->type === Token::TYPE_KEYWORD && is_string($nextToken->keyword)) {
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
        if ($operator === null) {
            $currentToken = $list->tokens[$list->idx] ?? null;
            if ($currentToken !== null && $currentToken->type === Token::TYPE_OPERATOR && $currentToken->value === '(') {
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
            
            if ($token->type === Token::TYPE_DELIMITER) {
                break;
            }

            if ($token->type === Token::TYPE_WHITESPACE || $token->type === Token::TYPE_COMMENT) {
                ++$list->idx;
                continue;
            }

            if ($token->type === Token::TYPE_OPERATOR && $token->value === '(') {
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

            if ($token->type === Token::TYPE_OPERATOR) {
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
            
            if ($token->type === Token::TYPE_DELIMITER) {
                break;
            }

            if ($token->type === Token::TYPE_WHITESPACE || $token->type === Token::TYPE_COMMENT) {
                ++$list->idx;
                continue;
            }

            if ($token->type === Token::TYPE_KEYWORD && is_string($token->keyword) && strtoupper($token->keyword) === 'AS') {
                ++$list->idx;
                // Skip whitespace
                while ($list->idx < $list->count && 
                       ($list->tokens[$list->idx]->type === Token::TYPE_WHITESPACE || 
                        $list->tokens[$list->idx]->type === Token::TYPE_COMMENT)) {
                    ++$list->idx;
                }
                
                // Get alias
                if ($list->idx < $list->count && is_string($list->tokens[$list->idx]->value)) {
                    $alias = $list->tokens[$list->idx]->value;
                    ++$list->idx;
                }
                break;
            } elseif (($token->type === Token::TYPE_NONE || $token->type === Token::TYPE_SYMBOL) && is_string($token->value)) {
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
        if (count($subqueryTokens) > 0) {
            $subqueryTokensList = new TokensList($subqueryTokens);
            $subqueryParser = new Parser('', false);
            $subqueryParser->list = $subqueryTokensList;
            
            try {
                $subqueryParser->parse();
                if (count($subqueryParser->statements) > 0) {
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