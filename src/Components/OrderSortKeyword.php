<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Components;

class OrderSortKeyword
{
    public const Asc = 'ASC';
    public const Desc = 'DESC';

    /**
     * Create an OrderSortKeyword from a string value.
     * Replicates the enum from() method for PHP 7.4 compatibility.
     *
     * @param string $value The string value ('ASC' or 'DESC')
     * @return string The corresponding constant value
     * @throws \ValueError When the value is not valid
     */
    public static function from(string $value): string
    {
        $upperValue = strtoupper($value);
        
        switch ($upperValue) {
            case 'ASC':
                return self::Asc;
            case 'DESC':
                return self::Desc;
            default:
                throw new \ValueError("'{$value}' is not a valid OrderSortKeyword");
        }
    }
}
