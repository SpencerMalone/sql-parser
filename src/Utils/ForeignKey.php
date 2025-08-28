<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Utils;

final class ForeignKey
{
    /**
     * @var string|null
     */
    public $constraint;

    /**
     * @var string[]
     */
    public $indexList;

    /**
     * @var string|null
     */
    public $refDbName;

    /**
     * @var string|null
     */
    public $refTableName;

    /**
     * @var string[]
     */
    public $refIndexList;

    /**
     * @var string|null
     */
    public $onUpdate;

    /**
     * @var string|null
     */
    public $onDelete;

    /**
     * @param string|null $constraint
     * @param string[]    $indexList
     * @param string|null $refDbName
     * @param string|null $refTableName
     * @param string[]    $refIndexList
     * @param string|null $onUpdate
     * @param string|null $onDelete
     */
    public function __construct(
        ?string $constraint = null,
        array $indexList = [],
        ?string $refDbName = null,
        ?string $refTableName = null,
        array $refIndexList = [],
        ?string $onUpdate = null,
        ?string $onDelete = null
    ) {
        $this->constraint = $constraint;
        $this->indexList = $indexList;
        $this->refDbName = $refDbName;
        $this->refTableName = $refTableName;
        $this->refIndexList = $refIndexList;
        $this->onUpdate = $onUpdate;
        $this->onDelete = $onDelete;
    }
}
