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

use MittagQI\ZfExtended\CsrfProtection;

/**
 * Represents an API-Client for the T5 API
 * Sets the correct environment & authorization cookie
 */
class ZfExtended_ApiClient extends Zend_Http_Client
{
    private string $translate5ApiUrl;

    /**
     * Creates a Cleient for requesting the T5 API For an API-request the authorization-cookie needs to be set
     * @param string|null $uri
     * @throws Zend_Exception
     * @throws Zend_Http_Client_Exception
     * @throws ZfExtended_Exception
     */
    public function __construct($uri = null, string $authorizationCookie = null, string $authorizationToken = null)
    {
        $this->translate5ApiUrl = self::getServerBaseURL();
        $config = Zend_Registry::get('config');
        parent::__construct($uri, $config); // why do we pass "our" config here ?
        // we need to trigger correct environment for request on our own API while API-testing
        // security: APPLICATION_APITEST can only be set, when the instance is set up for testing
        if (defined('APPLICATION_APITEST') && APPLICATION_APITEST) {
            $origin = (APPLICATION_ENV === ZfExtended_BaseIndex::ENVIRONMENT_TEST) ? ZfExtended_BaseIndex::ORIGIN_TEST : ZfExtended_BaseIndex::ORIGIN_APPTEST;
            $this->setHeaders('Origin', $origin);
        }

        // use token if given from outside or used by current request
        if ($authorizationToken === null) {
            $authorizationToken = ZfExtended_Authentication::getInstance()->getUsedToken();
        }

        if ($authorizationToken !== null && strlen($authorizationToken) > 0) {
            $this->setHeaders(ZfExtended_Authentication::APPLICATION_TOKEN_HEADER . ': ' . $authorizationToken);

            return;
        }

        //by default use the session cookie
        $authCookieName = Zend_Registry::get('config')->resources->ZfExtended_Resource_Session->name;
        if ($authorizationCookie === null) {
            if (! array_key_exists($authCookieName, $_COOKIE)) {
                throw new ZfExtended_Exception('ZfExtended_ApiClient: Authorization Cookie is not set.');
            }
            $authorizationCookie = $_COOKIE[$authCookieName];
        }
        $this->setCookie($authCookieName, $authorizationCookie);
        // add CSRF protection
        CsrfProtection::getInstance()->addRequestHeaders($this);
    }

    /**
     * Overridden to set the correct Host & Scheme
     * @param string|Zend_Uri_Http $uri
     * @return $this|ZfExtended_ApiClient
     * @throws Zend_Exception
     * @throws Zend_Http_Client_Exception
     * @throws Zend_Uri_Exception
     */
    public function setUri($uri)
    {
        // complement T5 base url
        if (is_string($uri)) {
            $uri = $this->setT5SchemeAndHost($uri);
        } elseif ($uri instanceof Zend_Uri_Http) {
            $uri = $this->setT5SchemeAndHost($uri->__toString());
        }

        return parent::setUri($uri);
    }

    /**
     * Replaces scheme & host in any URL to the T5 one
     */
    private function setT5SchemeAndHost(string $url): string
    {
        if (str_contains($url, '://')) {
            $parts = explode('://', $url);
            $parts = explode('/', $parts[1]);
            if (count($parts) === 0) {
                return $this->translate5ApiUrl;
            }
            array_shift($parts);

            return $this->translate5ApiUrl . '/' . implode('/', $parts);
        }

        return $this->translate5ApiUrl . '/' . ltrim($url, '/');
    }

    /**
     * returns the server base URL with scheme
     * @throws Zend_Exception
     */
    public static function getServerBaseURL(): string
    {
        $config = Zend_Registry::get('config');
        $url = $config->runtimeOptions->worker->server;
        if (empty($url)) {
            return $config->runtimeOptions->server->protocol . $config->runtimeOptions->server->name;
        }

        return $url;
    }
}
