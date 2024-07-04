<?php

namespace MittagQI\ZfExtended\Models\Filter;

/**
 * Validate filter and sort fields against the actual table fields.
 */
class FilterValidator implements ValidatorInterface
{

    public function validate(string $field, array $allowedFields, array $fieldMappings): bool
    {
        if(isset($fieldMappings[$field]))
        {
            $field = $fieldMappings[$field];
        }
        return in_array($field, $allowedFields);
    }
}