<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library

 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

namespace MittagQI\ZfExtended\Zendoverwrites\Http;

use stdClass;
use Zend_Http_Response;
use ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse as InvalidResponse;

/**
 * Overwritten HTTP Client
 * - Adds the config parameter "removeArrayIndexInUrlEncode", if true the [] in a post with multiple same named parameter are removed
 * - Adds debugging capabilities
 */
class JsonResponse
{
    public const VALID_STATES = [200, 201, 204];

    protected mixed $data;

    protected ?stdClass $error = null;

    protected Zend_Http_Response $response;

    protected string $url;

    protected string $method;

    public function hasError(): bool
    {
        return ! is_null($this->error);
    }

    public function getError(): ?stdClass
    {
        return $this->error;
    }

    public function getResponse(): Zend_Http_Response
    {
        return $this->response;
    }

    /**
     * Convenience API
     */
    public function getResponseBody(): ?string
    {
        return $this->response->getBody();
    }

    /**
     * Convenience API
     */
    public function getRawResponseBody(): ?string
    {
        return $this->response->getRawBody();
    }

    /**
     * Convenience API
     */
    public function getStatus(): int
    {
        return $this->response->getStatus();
    }

    /**
     * Convenience API
     */
    public function isStatusValid(): bool
    {
        return in_array($this->response->getStatus(), self::VALID_STATES);
    }

    /**
     * Checks wether the result is not Empty. Empty means only an empty response-string was sent
     */
    public function isEmpty(): bool
    {
        return ! $this->hasData() || $this->data === '';
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Retrieves if data was received
     * This may not be the case when errors occured
     * To check for empty result, use isEmpty
     */
    public function hasData(): bool
    {
        return isset($this->data);
    }

    /**
     * Retrieves if the retrieved data is an object
     */
    public function hasDataObject(): bool
    {
        return $this->hasData() && is_object($this->data);
    }

    /**
     * Retrieves if the retrieved data is an array
     */
    public function hasDataArray(): bool
    {
        return $this->hasData() && is_array($this->data);
    }

    /**
     * @throws InvalidResponse
     */
    public function __construct(Zend_Http_Response $response, string $uri, string $method)
    {
        $this->url = $uri;
        $this->method = $method;
        $this->processResponse($response);
    }

    /**
     * parses and processes the response of OpenTM2, and handles the errors
     * @throws InvalidResponse
     */
    protected function processResponse(Zend_Http_Response $response): void
    {
        //example how to fake a response
        //$response = new Zend_Http_Response(500, [], '{"ReturnValue":0,"ErrorMsg":"Error: too many open translation memory databases"}');
        $this->error = null;
        $this->response = $response;

        // FIXME check for returned content type

        //check for HTTP State (REST errors)
        if (! $this->isStatusValid()) {
            $this->error = $this->createError();
        }

        $responseBody = trim($response->getBody());
        // We cast any empty response to an empty string
        if ($responseBody === null || $responseBody === '') {
            $this->data = '';

            return;
        }

        $errorExtra = [
            //'method' => $this->method,
            //'url' => $this->url,
        ];
        $this->data = json_decode($responseBody);

        $lastJsonError = json_last_error();

        //if the json string contains unescapd ctrl characters, we escape them and try again the decode:
        if ($lastJsonError == JSON_ERROR_CTRL_CHAR) {
            //set the previous responseBody in case of an error
            $errorExtra['rawanswerBeforeCtrlCharFix'] = $responseBody;

            //escape control characters with \u notation
            $responseBody = preg_replace_callback('/[[:cntrl:]]/', function ($x) {
                return substr(json_encode($x[0]), 1, -1);
            }, $responseBody);
            $this->data = json_decode($responseBody);

            //get json error to proceed as usual
            $lastJsonError = json_last_error();
        }

        //check for JSON errors
        if ($lastJsonError != JSON_ERROR_NONE) {
            $errorExtra['errorMsg'] = json_last_error_msg();
            $errorExtra['rawanswer'] = $responseBody;

            throw new InvalidResponse('E1510', $errorExtra);
        }
    }

    /**
     * Creates an error for the Response
     * Caution: Even creates an Error, when the request was successful
     */
    public function createError(): stdClass
    {
        $error = new stdClass();
        $error->url = $this->url;
        $error->method = $this->method;
        $error->type = 'HTTP ' . $this->response->getStatus();
        $error->body = $this->response->getBody();
        $error->error = $this->response->getStatus(); //is normally overwritten later

        return $error;
    }
}
