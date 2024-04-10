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

use Zend_Controller_Response_Abstract;
use ZfExtended_Authentication;

/*

        Example Preflight Request Response:
        HTTP/1.1 204 No Content
        Date: Mon, 01 Dec 2008 01:15:39 GMT
        Server: Apache/2
        Access-Control-Allow-Origin: https://foo.example
        Access-Control-Allow-Methods: POST, GET, OPTIONS
        Access-Control-Allow-Headers: X-PINGOTHER, Content-Type
        Access-Control-Max-Age: 86400
        Vary: Accept-Encoding, Origin
        Keep-Alive: timeout=2, max=100
        Connection: Keep-Alive

*/

/**
 * Provides APIs to manage CORS Requests and add CORS headers to requests
 */
final class Cors
{
    /**
     * Handles a preflight (a request to find out, what kind of request is allowed)
     * This should be handled with an apache-config or .htaccess normally and is just a fallback-implementation
     * A preflight is signalled by the "OPTIONS" header being sent
     */
    public static function handlePreflight()
    {
        if (array_key_exists('REQUEST_METHOD', $_SERVER) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Max-Age: 86400');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, Origin, ' . ZfExtended_Authentication::APPLICATION_TOKEN_HEADER);
            header('Access-Control-Allow-Origin: *');
            error_log('Responding CORS preflight request');
            exit(0);
        }
    }

    public static function addResponseHeader(Zend_Controller_Response_Abstract $response)
    {
        if (ZfExtended_Authentication::isAppTokenAuthenticated()) {
            $response->setHeader('Access-Control-Allow-Origin', '*', true);
        }
    }

    public static function sendResponseHeader()
    {
        if (ZfExtended_Authentication::isAppTokenAuthenticated()) {
            header('Access-Control-Allow-Origin: *', true);
        }
    }
}
