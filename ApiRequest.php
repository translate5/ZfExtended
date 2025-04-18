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

namespace MittagQI\ZfExtended;

use Zend_Exception;
use Zend_Http_Client_Exception;
use Zend_Http_Response;
use Zend_Registry;
use ZfExtended_ApiClient;
use ZfExtended_Exception;
use ZfExtended_FileUploadException;
use ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse;

/**
 * Represents a single API-Request on the T5 API
 */
final class ApiRequest
{
    public const CONTENT_TYPE = 'application/json';

    public const ACCEPT = 'application/json; charset=utf-8';

    public const VALID_RESPONSE_STATES = [200, 201];

    /**
     * Helper to perform a T5 api-request with a DTO
     * @throws Zend_Exception
     * @throws Zend_Http_Client_Exception
     * @throws ZfExtended_Exception
     * @throws ZfExtended_FileUploadException
     */
    public static function requestApi(ApiRequestDTO $dto): mixed
    {
        $request = new self();

        try {
            return $request->fetch($dto->method, $dto->endpoint, $dto->rawData, $dto->params, $dto->files);
        } catch (ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse $e) {
            $logger = Zend_Registry::get('logger')->cloneMe($dto->loggerDomain);
            $logger->exception($e, [
                'level' => $logger::LEVEL_WARN,
            ]);
        }

        return null;
    }

    /**
     * If no explicit application-token is passed, the auth-cookie or the auth-token of the current request will be used
     */
    public function __construct(
        private readonly ?string $applicationToken = null
    ) {
    }

    /**
     * fetches the passed request configuration
     * @throws ZfExtended_FileUploadException
     * @throws ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse
     * @throws Zend_Exception
     * @throws Zend_Http_Client_Exception
     * @throws ZfExtended_Exception
     */
    public function fetch(
        string $method,
        string $endpointPath = '',
        array $rawData = [],
        array $queryParams = [],
        array $file = []
    ): mixed {
        $client = new ZfExtended_ApiClient($endpointPath, authorizationToken: $this->applicationToken);
        $client->setMethod($method);
        $client->setHeaders('Content-Type: ' . self::CONTENT_TYPE);
        $client->setHeaders('Accept: ' . self::ACCEPT);

        if (! empty($queryParams)) {
            if ($method == 'POST' || $method == 'PUT') {
                $addParamsMethod = 'setParameterPost';
            } else {
                $addParamsMethod = 'setParameterGet';
            }
            foreach ($queryParams as $key => $value) {
                $client->$addParamsMethod($key, $value);
            }
        }

        if (! empty($rawData)) {
            $client->setRawData(json_encode($rawData), self::ACCEPT); // TODO: not tested so far
        }

        if (! empty($file)) {
            foreach ($file as $formname => $fileInfo) {
                $this->checkFileSize($fileInfo);

                $client->setFileUpload(
                    $fileInfo['name'],
                    $formname,
                    $fileInfo['data'] ?? file_get_contents($fileInfo['tmp_name']),
                    $fileInfo['type'] ?? null
                );
            }
        }

        return $this->processResponse($client->request($method));
    }

    /**
     * Check and throw exception for given file-upload
     * @throws ZfExtended_FileUploadException
     */
    private function checkFileSize(array $fileInfo): void
    {
        if (isset($fileInfo['error']) && $fileInfo['error'] > 0) {
            throw new ZfExtended_FileUploadException('E1211', [
                'msg' => ZfExtended_FileUploadException::getUploadErrorMessage($fileInfo['error']),
            ]);
        }
    }

    /**
     * translate5-API: Parses and processes the response
     * @throws ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse
     */
    private function processResponse(Zend_Http_Response $response): mixed
    {
        // check for HTTP State (REST errors)
        if (! in_array($response->getStatus(), self::VALID_RESPONSE_STATES)) {
            throw new ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse('E1207', [
                'status' => $response->getStatus(),
                'response' => (string) $response,
            ]);
        }

        $responseBody = trim($response->getBody());
        $result = (empty($responseBody)) ? '' : json_decode($responseBody);

        // check for JSON errors: parse error in JSON response
        if (json_last_error() > 0) {
            throw new ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse('E1208', [
                'msg' => json_last_error_msg(),
                'response' => (string) $response,
            ]);
        }

        // empty JSON response
        if (empty($result) && strlen($result) == 0) {
            throw new ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse('E1209', [
                'response' => (string) $response,
            ]);
        }

        return $result;
    }
}
