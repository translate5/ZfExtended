<?php

namespace MittagQI\ZfExtended\Models\Filter;

use Zend_Db_Select;

/**
 * Validate filter and sort fields against the actual table fields.
 */
final class FilterJoinDTO
{
    public function __construct(
        public string $table,
        public string $localKey,
        public string $foreignKey,
        public array $columns = [],
        public ?string $localAlias = null,
        public string $joinType = Zend_Db_Select::INNER_JOIN,
    ) {
    }

    /**
     * Creates an identification-hash
     */
    public function getIdentifier(): string
    {
        return $this->table . '#' . $this->localKey . '#' . $this->foreignKey;
    }
}
