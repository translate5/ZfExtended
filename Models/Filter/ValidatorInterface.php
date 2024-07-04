<?php

namespace MittagQI\ZfExtended\Models\Filter;

interface ValidatorInterface
{
    public function validate(string $field, array $allowedFields, array $fieldMappings): bool;
}