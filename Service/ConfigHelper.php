<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library

 Copyright (c) 2013 - 2022 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file lgpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
https://www.gnu.org/licenses/lgpl-3.0.txt

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU LESSER GENERAL PUBLIC LICENSE version 3
			 https://www.gnu.org/licenses/lgpl-3.0.txt

END LICENSE AND COPYRIGHT
*/

namespace MittagQI\ZfExtended\Service;

use Exception;
use Throwable;
use Zend_Config;
use ZfExtended_DbConfig_Type_CoreTypes as CoreTypes;
use ZfExtended_Exception;

/**
 * Helper to access ZendConfig Ojects with keys like 'runtimeOptions.pluginName.configName'
 * Also allows overriding keys temporarily with a registry (mainly to create mock overrides for Services)
 */
final class ConfigHelper
{
    /**
     * When mock-values are set, this can be used to reference the installations current base-url
     */
    const BASE_URL = '{BASE_URL}';

    /**
     * Retrieves if a config value can be seen as empty
     * This is always used to evaluate if configs are set in services
     * @param mixed $value
     * @return bool
     */
    public static function isValueEmpty(mixed $value): bool
    {
        return
            $value === null
            || (is_array($value) && empty($value))
            || (is_string($value) && strlen($value) < 1)
            || (is_int($value) && $value === 0)
            || (is_float($value) && $value == 0);
    }

    /**
     * @param Zend_Config $config
     */
    public function __construct(private Zend_Config $config, private array $overrides = [])
    {
    }

    /**
     * Retrieves a config value by providing the full "path" like 'runtimeOptions.pluginName.configName'
     * @param string $configName
     * @param string|null $configType
     * @param bool $asArray
     * @return mixed
     * @throws ZfExtended_Exception
     */
    public function getValue(string $configName, string $configType = 'notype', bool $asArray = false): mixed
    {
        if (array_key_exists($configName, $this->overrides)) {
            return $this->getOverriddenValue($configName, $asArray);
        }
        $value = $this->config;
        try {
            foreach (explode('.', $configName) as $section) {
                // to avoid warnings ...
                if (empty($value)) {
                    throw new Exception('Value is null');
                }
                $value = $value->$section;
            }
        } catch (Throwable) {
            throw new ZfExtended_Exception('Global Config did not contain "' . $configName . '"');
        }
        return match ($configType) {
            CoreTypes::TYPE_LIST,
            CoreTypes::TYPE_MAP,
            CoreTypes::TYPE_REGEXLIST => $value->toArray(),
            'notype' => $this->formatValue($value, $asArray),
            default => $this->asArray(CoreTypes::setPhpType($value, $configType), $asArray)
        };
    }

    /**
     * Checks wether a config value is set and not empty
     * It counts always as being set, if it is in the overrides
     * Empty arrays, 0, empty strings will be seen as non-existant, if they should count use strict-param
     * @param string $configName
     * @param bool $strict: if given, only null /non-existant will lead to a false evaluation
     * @return bool
     */
    public function hasValue(string $configName, bool $strict = false): bool
    {
        if (array_key_exists($configName, $this->overrides)) {
            return true;
        }
        $value = $this->config;
        try {
            foreach (explode('.', $configName) as $section) {
                // to avoid warnings ...
                if(empty($value)){
                    throw new Exception('Value is null');
                }
                $value = $value->$section;
            }
        } catch (Throwable) {
            return false;
        }
        if ($value instanceof Zend_Config) {
            return $strict ? true : !empty($value->toArray());
        }
        return $strict ? ($value !== null) : !self::isValueEmpty($value);
    }

    /**
     * @param string $configName
     * @param mixed $value
     * @return void
     * @throws ZfExtended_Exception
     */
    public function setValue(string $configName, mixed $value): void
    {
        $this->overrides[$configName] = $value;
    }

    /**
     * @param array $values
     * @return void
     */
    public function setValues(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $this->overrides[$key] = $value;
            }
        }
    }

    /**
     * Helper to create array-values
     * @param mixed $value
     * @return array
     */
    public function convertValueToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return (empty($value) && $value != '0') ? [] : [$value];
    }

    /**
     * Formats the given value
     * Zend_Config objects will be returned as assoc arrays
     * @param mixed $value
     * @param bool $asArray
     * @return mixed
     */
    private function formatValue(mixed $value, bool $asArray): mixed
    {
        if (is_object($value) && get_class($value) === 'Zend_Config') {
            return $value->toArray();
        }
        return $this->asArray($value, $asArray);
    }

    /**
     * Turns an value to an array - if wanted
     * @param mixed $value
     * @param bool $asArray
     * @return mixed
     */
    private function asArray(mixed $value, bool $asArray): mixed
    {
        if($asArray) {
            return $this->convertValueToArray($value);
        }
        return $value;
    }

    /**
     * Retrieves a overridden value which may holds mock-api URLs
     * @param string $key
     * @return mixed
     */
    private function getOverriddenValue(string $key, bool $asArray): mixed
    {
        $value = $this->overrides[$key];
        if (is_string($value) && str_contains($value, self::BASE_URL)) {
            $value = str_replace(self::BASE_URL, $this->getBaseUrl(), $value);
        }
        return $asArray ? $this->convertValueToArray($value) : $value;
    }

    /**
     * @return string
     */
    private function getBaseUrl(): string
    {
        return
            $this->config->runtimeOptions->server->protocol
            . $this->config->runtimeOptions->server->name;
    }
}
