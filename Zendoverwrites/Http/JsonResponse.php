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
    /**
     * @var mixed
     */
    protected mixed $data;

    protected ?\stdClass $error = null;

    /**
     * @var Zend_Http_Response
     */
    protected Zend_Http_Response $response;

    /**
     * @return bool
     */
    public function hasError(): bool
    {
        return !is_null($this->error);
    }

    /**
     * @return stdClass|null
     */
    public function getError(): ?stdClass
    {
        return $this->error;
    }

    /**
     * @return Zend_Http_Response
     */
    public function getResponse(): Zend_Http_Response
    {
        return $this->response;
    }

    /**
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @throws InvalidResponse
     */
    public function __construct(Zend_Http_Response $response) {
        $this->processResponse($response);
    }

    /**
     * parses and processes the response of OpenTM2, and handles the errors
     * @param Zend_Http_Response $response
     * @return boolean
     * @throws InvalidResponse
     */
    protected function processResponse(Zend_Http_Response $response): bool {
        //example how to fake a response
        //$response = new Zend_Http_Response(500, [], '{"ReturnValue":0,"ErrorMsg":"Error: too many open translation memory databases"}');
        $this->error = null;
        $this->response = $response;
        $validStates = [200, 201, 204];

        //$url = $this->http->getUri(true);

        // FIXME check for returned content type

        //check for HTTP State (REST errors)
        if (!in_array($response->getStatus(), $validStates)) {
            $this->error = new stdClass();
            $this->error->method = $this->httpMethod;
            //$this->error->url = $url;
            $this->error->type = 'HTTP '.$response->getStatus();
            $this->error->body = $response->getBody();
            $this->error->error = $response->getStatus(); //is normally overwritten later
        }

        $responseBody = trim($response->getBody());

        if (empty($responseBody)) {
            $this->data = '';
            return empty($this->error);
        }

        $errorExtra = [
            //'method' => $this->httpMethod,
            //'url' => $url,
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

        return empty($this->error);
    }


}
