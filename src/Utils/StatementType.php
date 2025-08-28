<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Utils;

class StatementType
{
    public const Alter = 'ALTER';
    public const Analyze = 'ANALYZE';
    public const Call = 'CALL';
    public const Check = 'CHECK';
    public const Checksum = 'CHECKSUM';
    public const Create = 'CREATE';
    public const Delete = 'DELETE';
    public const Drop = 'DROP';
    public const Explain = 'EXPLAIN';
    public const Insert = 'INSERT';
    public const Kill = 'KILL';
    public const Load = 'LOAD';
    public const Optimize = 'OPTIMIZE';
    public const Repair = 'REPAIR';
    public const Replace = 'REPLACE';
    public const Select = 'SELECT';
    public const Set = 'SET';
    public const Show = 'SHOW';
    public const Update = 'UPDATE';
}
