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
use ZfExtended_DbConfig_Type_CoreTypes;
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
     * @param Zend_Config $config
     */
    public function __construct(private Zend_Config $config, private array $overrides = [])
    {
    }

    /**
     * Retrieves a config value by providing the full "path" like 'runtimeOptions.pluginName.configName'
     * @param string $configName
     * @param string $configType
     * @param bool $asArray
     * @return mixed
     * @throws ZfExtended_Exception
     */
    public function getValue(string $configName, string $configType = 'string', bool $asArray = false): mixed
    {
        if (array_key_exists($configName, $this->overrides)) {
            return $this->getOverriddenValue($configName, $asArray);
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
            throw new ZfExtended_Exception('Global Config did not contain "' . $configName . '"');
        }
        return match ($configType) {
            ZfExtended_DbConfig_Type_CoreTypes::TYPE_LIST,
            ZfExtended_DbConfig_Type_CoreTypes::TYPE_MAP,
            ZfExtended_DbConfig_Type_CoreTypes::TYPE_REGEXLIST => $value->toArray(),
            default => $this->formatValue($value, $asArray)
        };
    }

    /**
     * Checks wether a config value is set and not empty
     * It counts always as being set if it is in the overrides
     * @param string $configName
     * @return bool
     */
    public function hasValue(string $configName): bool
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
            return !empty($value->toArray());
        } else if (is_string($value)) {
            return $value !== null && strlen($value) > 0;
        } else if (is_float($value) || is_int($value)) {
            return $value !== 0;
        }
        return !empty($value);
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
        } else if ($asArray) {
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
