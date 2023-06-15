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

use Zend_Http_Client;
use Zend_Http_Client_Exception;
use ZfExtended_Zendoverwrites_Http_Client;
use ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse;

/**
 * Overwritten HTTP Client for easy JSON handling
 */
class JsonClient extends ZfExtended_Zendoverwrites_Http_Client
{
    /**
     * @throws Zend_Http_Client_Exception
     */
    public function __construct($uri = null, $config = null)
    {
        parent::__construct($uri, $config);
        $this->setHeaders('Content-Type', 'application/json');
    }

    /**
     * @param string $method
     * @param mixed|null $data
     * @return JsonResponse
     * @throws Zend_Http_Client_Exception
     * @throws ZfExtended_Zendoverwrites_Http_Exception_InvalidResponse
     */
    public function requestJson(string $method = Zend_Http_Client::GET, mixed $data = null): JsonResponse
    {
        if (!is_null($data)) {
            $this->setRawData(json_encode($data), 'application/json');
        }
        return new JsonResponse($this->request($method), $this->getUri(true), $method);
    }
}
