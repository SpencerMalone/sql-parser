<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Parsers;

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Parseable;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokensList;
use PhpMyAdmin\SqlParser\TokenType;

use function implode;
use function in_array;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * `WHERE` keyword parser.
 */
final class Conditions implements Parseable
{
    /**
     * Logical operators that can be used to delimit expressions.
     */
    private const DELIMITERS = [
        '&&',
        '||',
        'AND',
        'OR',
        'XOR',
    ];

    /**
     * List of allowed reserved keywords in conditions.
     */
    private const ALLOWED_KEYWORDS = [
        'ALL',
        'AND',
        'BETWEEN',
        'COLLATE',
        'EXISTS',
        'IF',
        'IN',
        'INTERVAL',
        'IS',
        'LIKE',
        'MATCH',
        'NOT IN',
        'NOT NULL',
        'NOT',
        'NULL',
        'OR',
        'REGEXP',
        'RLIKE',
        'SOUNDS',
        'XOR',
    ];

    private const COMPARISON_OPERATORS = ['=', '>=', '>', '<=', '<', '<>', '!='];

    /**
     * @param Parser               $parser  the parser that serves as context
     * @param TokensList           $list    the list of tokens that are being parsed
     * @param array<string, mixed> $options parameters for parsing
     *
     * @return Condition[]
     */
    public static function parse(Parser $parser, TokensList $list, array $options = []): array
    {
        $ret = [];

        $expr = new Condition();

        /**
         * Counts brackets.
         */
        $brackets = 0;

        /**
         * Whether there was a `BETWEEN` keyword before or not.
         *
         * It is required to keep track of them because their structure contains
         * the keyword `AND`, which is also an operator that delimits
         * expressions.
         */
        $betweenBefore = false;

        $hasSubQuery = false;
        $subQueryBracket = 0;

        for (; $list->idx < $list->count; ++$list->idx) {
            /**
             * Token parsed at this moment.
             */
            $token = $list->tokens[$list->idx];

            // End of statement.
            if ($token->type === TokenType::Delimiter) {
                break;
            }

            // Skipping whitespaces and comments.
            if ($token->type === TokenType::Comment) {
                continue;
            }

            // Replacing all whitespaces (new lines, tabs, etc.) with a single
            // space character.
            if ($token->type === TokenType::Whitespace) {
                $expr->expr .= ' ';
                if ($expr->operator === '') {
                    $expr->leftOperand .= ' ';
                } else {
                    $expr->rightOperand .= ' ';
                }

                continue;
            }

            if (
                ! $hasSubQuery
                && $token->keyword !== null && $token->type === TokenType::Keyword
                && $brackets > 0
                && (Parser::STATEMENT_PARSERS[$token->keyword] ?? '') !== ''
            ) {
                $hasSubQuery = true;
                $subQueryBracket = $brackets;
            }

            // Conditions are delimited by logical operators.
            if (
                ($token->type === TokenType::Keyword || $token->type === TokenType::Operator)
                && in_array($token->value, self::DELIMITERS, true)
            ) {
                if ($betweenBefore && ($token->value === 'AND')) {
                    // The syntax of keyword `BETWEEN` is hard-coded.
                    $betweenBefore = false;
                } else {
                    // The expression ended.
                    $expr->expr = trim($expr->expr);
                    if ($expr->expr !== '') {
                        $expr->leftOperand = trim($expr->leftOperand);
                        $expr->rightOperand = trim($expr->rightOperand);
                        $ret[] = $expr;
                    }

                    // Adding the operator.
                    $expr = new Condition($token->value);
                    $expr->isOperator = true;
                    $ret[] = $expr;

                    // Preparing to parse another condition.
                    $expr = new Condition();
                    continue;
                }
            }

            if (
                ($token->type === TokenType::Keyword)
                && ($token->flags & Token::FLAG_KEYWORD_RESERVED)
                && ! ($token->flags & Token::FLAG_KEYWORD_FUNCTION)
            ) {
                if ($token->value === 'BETWEEN') {
                    $betweenBefore = true;
                }

                if ($brackets === 0 && ! in_array($token->value, self::ALLOWED_KEYWORDS, true)) {
                    break;
                }
            }

            if ($token->type === TokenType::Operator) {
                if ($token->value === '(') {
                    ++$brackets;
                } elseif ($token->value === ')') {
                    if ($brackets === 0) {
                        break;
                    }

                    if ($subQueryBracket === $brackets) {
                        $hasSubQuery = false;
                    }

                    --$brackets;
                } elseif (! $hasSubQuery && in_array($token->value, self::COMPARISON_OPERATORS, true)) {
                    $expr->operator = $token->value;
                }
            }

            $expr->expr .= $token->token;
            if ($expr->operator === '') {
                $expr->leftOperand .= $token->token;
            } elseif ($expr->rightOperand !== '' || $expr->operator !== $token->value) {
                $expr->rightOperand .= $token->token;
            }

            if (
                ($token->type !== TokenType::None)
                && (($token->type !== TokenType::Keyword)
                || ($token->flags & Token::FLAG_KEYWORD_RESERVED))
                && ($token->type !== TokenType::String)
                && ($token->type !== TokenType::Symbol || ($token->flags & Token::FLAG_SYMBOL_PARAMETER))
            ) {
                continue;
            }

            if (in_array($token->value, $expr->identifiers)) {
                continue;
            }

            $expr->identifiers[] = $token->value;
        }

        // Last iteration was not processed.
        $expr->expr = trim($expr->expr);
        if ($expr->expr !== '') {
            $expr->leftOperand = trim($expr->leftOperand);
            $expr->rightOperand = trim($expr->rightOperand);
            
            // Check if the condition contains subqueries and parse them
            self::parseSubqueries($parser, $expr);
            
            $ret[] = $expr;
        }

        --$list->idx;

        return $ret;
    }

    /** @param Condition[] $component the component to be built */
    public static function buildAll(array $component): string
    {
        return implode(' ', $component);
    }

    /**
     * Parses subqueries within a condition and stores them as structured components.
     *
     * @param Parser    $parser the parser context
     * @param Condition $condition the condition to analyze for subqueries
     */
    private static function parseSubqueries(Parser $parser, Condition $condition): void
    {
        // Look for subquery patterns in the expression
        $expr = $condition->expr;
        
        // Find all subquery patterns: (SELECT ...)
        if (strpos($expr, '(SELECT') !== false) {
            $components = [];
            $lastPos = 0;
            
            // Find each subquery
            $pos = strpos($expr, '(SELECT', $lastPos);
            while ($pos !== false) {
                // Add text before the subquery as an Expression component
                if ($pos > $lastPos) {
                    $beforeText = substr($expr, $lastPos, $pos - $lastPos);
                    $components[] = new Expression($beforeText);
                }
                
                // Find the matching closing parenthesis
                $depth = 0;
                $end = $pos;
                
                for ($i = $pos; $i < strlen($expr); $i++) {
                    if ($expr[$i] === '(') $depth++;
                    if ($expr[$i] === ')') $depth--;
                    if ($depth === 0) {
                        $end = $i + 1;
                        break;
                    }
                }
                
                // Extract and parse the subquery
                $subquerySql = substr($expr, $pos, $end - $pos);
                $lexer = new \PhpMyAdmin\SqlParser\Lexer($subquerySql);
                $subqueryParser = new \PhpMyAdmin\SqlParser\Parser('', false);
                
                $subquery = \PhpMyAdmin\SqlParser\Parsers\SubqueryExpressions::parse($subqueryParser, $lexer->list);
                
                if ($subquery) {
                    $components[] = $subquery;
                } else {
                    // If parsing fails, keep as text
                    $components[] = new Expression($subquerySql);
                }
                
                // Copy any errors from subquery parser
                $parser->errors = array_merge($parser->errors, $subqueryParser->errors);
                
                // Move to next potential subquery
                $lastPos = $end;
                $pos = strpos($expr, '(SELECT', $lastPos);
            }
            
            // Add any remaining text
            if ($lastPos < strlen($expr)) {
                $remainingText = substr($expr, $lastPos);
                $components[] = new Expression($remainingText);
            }
            
            // Store the components if we found any subqueries
            if (count($components) > 1) { // More than one component means we found subqueries
                $condition->components = $components;
            }
        }
    }
}
