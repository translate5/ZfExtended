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

namespace MittagQI\ZfExtended\Sanitizer;

use JsonException;
use MittagQI\ZfExtended\Cors;
use MittagQI\ZfExtended\Sanitizer;
use REST_Controller_Request_Http;
use Zend_Controller_Request_Exception;
use ZfExtended_BadRequest;
use ZfExtended_SecurityException;

/**
 * Base Request Class
 * Sanitizes all parameters but not 'data' which shall be retrieved with ::getData to properly support JSON params
 */
class HttpRequest extends REST_Controller_Request_Http
{
    private array $explicitlySet = [];

    public function __construct($action = null, $controller = null, $module = null, array $params = [])
    {
        // processing CORS preflight requests
        Cors::handlePreflight();
        parent::__construct($action, $controller, $module, $params);
    }

    /**
     * Retrieves all GET/POST params sanitized
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        if (isset($this->_params[$key])) {
            return $this->_params[$key];
        } elseif ($key != 'data' && isset($_GET[$key])) {
            return $this->sanitizeRequestValue($_GET[$key]);
        } elseif ($key != 'data' && isset($_POST[$key])) {
            return $this->sanitizeRequestValue($_POST[$key]);
        }

        return parent::__get($key);
    }

    /**
     * Retrieves the raw unsanitized request params
     * @return mixed
     */
    public function getRaw(string $key)
    {
        return parent::__get($key);
    }

    /**
     * Retrieves a sanitized request parameter
     * @param mixed $key
     * @param mixed $default Default value to use if key not found
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        $keyName = (null !== ($alias = $this->getAlias($key))) ? $alias : $key;
        $paramSources = $this->getParamSources();
        if (isset($this->_params[$keyName])) {
            return $this->_params[$keyName];
        } elseif ($keyName != 'data' && in_array('_GET', $paramSources) && isset($_GET[$keyName])) {
            return $this->sanitizeRequestValue($_GET[$keyName]);
        } elseif ($keyName != 'data' && in_array('_POST', $paramSources) && isset($_POST[$keyName])) {
            return $this->sanitizeRequestValue($_POST[$keyName]);
        }

        return $default;
    }

    public function setParam($key, $value)
    {
        $this->explicitlySet[$key] = true;
        parent::setParam($key, $value);

        return $this;
    }

    /**
     * Retrieves an unsanitized request parameter
     * @param mixed $default
     * @return mixed
     */
    public function getRawParam(string $key, $default = null)
    {
        return parent::getParam($key, $default);
    }

    /**
     * Retrieves a sanitized array of all parameters
     * If some param already exists in $this->_params - it won't be overwritten by $_POST or $_GET
     * @return array
     */
    public function getParams()
    {
        $return = $this->_params;
        $paramSources = $this->getParamSources();
        if (in_array('_GET', $paramSources) && isset($_GET) && is_array($_GET)) {
            foreach ($_GET as $key => $val) {
                if (array_key_exists($key, $this->explicitlySet)) {
                    continue;
                }

                $return[$key] = ($key === 'data' || $key === 'filter') ? $val : $this->sanitizeRequestValue($val);
            }
        }
        if (in_array('_POST', $paramSources) && isset($_POST) && is_array($_POST)) {
            foreach ($_POST as $key => $val) {
                if (array_key_exists($key, $this->explicitlySet)) {
                    continue;
                }

                $return[$key] = ($key === 'data') ? $val : $this->sanitizeRequestValue($val);
            }
        }

        return $return;
    }

    /**
     * Retrieves an unsanitized array of all parameters
     * @return array
     */
    public function getRawParams()
    {
        return parent::getParams();
    }

    /**
     * Sanitizes the "data" param that represents the JSON data of a PUT or POST
     * @param array<Type> $typeMap
     * @throws Zend_Controller_Request_Exception
     * @throws ZfExtended_BadRequest
     * @throws ZfExtended_SecurityException
     */
    public function getData(bool $decodeAssociative, array $typeMap = []): mixed
    {
        $hasLegacyDataField = parent::has('data');
        if ($hasLegacyDataField) {
            $data = parent::getParam('data');
        } else {
            // if the request does not contain the data field, we assume JSON in raw body if content type is appropriate
            if ($this->getHeader('content-type') !== 'application/json') {
                return null;
            }
            $data = $this->getRawBody();
        }

        try {
            $data = json_decode($data, $decodeAssociative, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ZfExtended_BadRequest('E1560', [
                'error' => $e->getMessage(),
            ], $e);
        }
        if (is_array($data)) {
            return $this->sanitizeArray($data, $typeMap);
        } elseif (is_object($data)) {
            return $this->sanitizeObject($data, $typeMap);
        } elseif (is_string($data)) {
            return Sanitizer::string($data);
        }

        // can only be number, bool or null here, so needs no sanitization
        return $data;
    }

    /**
     * Retrieves the raw data-request param
     * @return mixed|string|null
     */
    public function getRawData($default = null)
    {
        return parent::getParam('data', $default);
    }

    /**
     * Sanitizes a single request value, which can only be string or array
     * @param array|string $requestValue
     * @return array|string
     */
    private function sanitizeRequestValue($requestValue)
    {
        if (is_array($requestValue)) {
            foreach ($requestValue as $key => $value) {
                $requestValue[$key] = $this->sanitizeRequestValue($value);
            }

            return $requestValue;
        }
        if (! empty($requestValue)) {
            return Sanitizer::string($requestValue);
        }

        return $requestValue;
    }

    /**
     * Helper for data-sanitization of arrays
     * @param array<Type> $typeMap
     * @throws ZfExtended_BadRequest
     * @throws ZfExtended_SecurityException
     */
    private function sanitizeArray(array $data, array $typeMap): array
    {
        foreach ($data as $key => $val) {
            if (is_string($val)) {
                $data[$key] = $this->sanitizeDataValue($key, $val, $typeMap);
            } elseif (is_array($val)) {
                $data[$key] = $this->sanitizeArray($val, []);
            } elseif (is_object($val)) {
                $data[$key] = $this->sanitizeObject($val, []);
            }
        }

        return $data;
    }

    /**
     * Helper for data-sanitization of stdClass Objects
     * @param array<Type> $typeMap
     * @throws ZfExtended_BadRequest
     * @throws ZfExtended_SecurityException
     */
    private function sanitizeObject(object $data, array $typeMap): object
    {
        foreach ($data as $key => $val) {
            if (is_string($val)) {
                $data->$key = $this->sanitizeDataValue($key, $val, $typeMap);
            } elseif (is_array($val)) {
                $data->$key = $this->sanitizeArray($val, []);
            } elseif (is_object($val)) {
                $data->$key = $this->sanitizeObject($val, []);
            }
        }

        return $data;
    }

    /**
     * Helper for data-sanitization
     * @param array<Type> $typeMap
     * @throws ZfExtended_BadRequest
     * @throws ZfExtended_SecurityException
     */
    private function sanitizeDataValue(string $key, string $val, array $typeMap): string
    {
        if (array_key_exists($key, $typeMap)) {
            return Sanitizer::sanitize($val, $typeMap[$key]);
        }

        return Sanitizer::string($val);
    }
}
