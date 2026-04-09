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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Abstract Class, with some general controller-methods
 *
 * - offers the default Zend_Session_Namespace in $this->session
 * - triggers the following Zend-Events for all controllers:
 *      - "beforeIndexAction" on preDispatch
 *      - "afterIndexAction" with parameter $this->view on postDispatch
 */
abstract class ZfExtended_Controllers_Action extends Zend_Controller_Action
{
    use ZfExtended_Controllers_MaintenanceTrait;

    /**
     * @var Zend_Session_Namespace
     */
    protected $_session = false;

    /**
     * @var ZfExtended_EventManager
     */
    protected $events = false;

    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = [])
    {
        parent::__construct($request, $response, $invokeArgs);
        $this->_helper = new ZfExtended_Zendoverwrites_Controller_Action_HelperBroker($this);
        if (! Zend_Session::isDestroyed()) {
            $this->_session = new Zend_Session_Namespace();
        }
        $this->events = ZfExtended_Factory::get('ZfExtended_EventManager', [get_class($this)]);
        $this->init();
    }

    public function init()
    {
        $this->view->controller = $this->_request->getControllerName();
        $this->view->action = $this->_request->getActionName();
    }

    /**
     * triggers event "before<Controllername>Action"
     */
    public function preDispatch()
    {
        // Mandatory security headers for all controllers
        $this->_response->setHeader('strict-transport-security', 'max-age=31536000; includeSubDomains; preload', true);
        $this->_response->setHeader('x-content-type-options', 'nosniff', true);
        $this->_response->setHeader('referrer-policy', 'strict-origin-when-cross-origin', true);

        // Optional security headers for all controllers
        $config = \Zend_Registry::get('config');
        if ($config->runtimeOptions->headers->enableXFrameHeader) {
            $this->_response->setHeader('x-frame-options', 'sameorigin', true);
        }

        $defaultSrc = "'self'";
        if (! empty($config->runtimeOptions->headers->defaultSrcUrls)) {
            $defaultSrc .= ' ' . $config->runtimeOptions->headers->defaultSrcUrls;
        }

        $scriptSrc = "'self' 'unsafe-inline' 'unsafe-eval' https://app.therootcause.io";
        if (! empty($config->runtimeOptions->headers->scriptSrcUrls)) {
            $scriptSrc .= ' ' . $config->runtimeOptions->headers->scriptSrcUrls;
        }

        $connectSrc = "'self'";
        $frontendMessageBusUrl = $config->runtimeOptions->plugins->FrontEndMessageBus->socketServer->httpHost;
        if ($frontendMessageBusUrl) {
            $connectSrc .= " wss://$frontendMessageBusUrl";
        }

        if (! empty($config->runtimeOptions->headers->connectSrcUrls)) {
            $connectSrc .= ' ' . $config->runtimeOptions->headers->connectSrcUrls;
        }

        $styleSrc = "'self' 'unsafe-inline' https://fonts.googleapis.com";
        if (! empty($config->runtimeOptions->headers->styleSrcUrls)) {
            $styleSrc .= ' ' . $config->runtimeOptions->headers->styleSrcUrls;
        }

        $imgSrc = "'self' data: https://www.translate5.net";
        if (! empty($config->runtimeOptions->headers->imgSrcUrls)) {
            $imgSrc .= ' ' . $config->runtimeOptions->headers->imgSrcUrls;
        }

        $fontSrc = "'self' https://fonts.googleapis.com https://fonts.gstatic.com";
        if (! empty($config->runtimeOptions->headers->fontSrcUrls)) {
            $fontSrc .= ' ' . $config->runtimeOptions->headers->fontSrcUrls;
        }

        $this->_response->setHeader(
            'content-security-policy',
            sprintf('default-src %s; script-src %s; connect-src %s; style-src %s; img-src %s; font-src %s;',
                $defaultSrc,
                $scriptSrc,
                $connectSrc,
                $styleSrc,
                $imgSrc,
                $fontSrc
            ),
            true
        );

        $this->displayMaintenance();
        $eventName = "before" . ucfirst($this->_request->getActionName()) . "Action";
        $this->events->trigger($eventName, $this, [
            'controller' => $this,
        ]);
    }

    /**
     * triggers event "after<Controllername>Action"
     */
    public function postDispatch()
    {
        $eventName = "after" . ucfirst($this->_request->getActionName()) . "Action";
        $this->events->trigger($eventName, $this, [
            'view' => $this->view,
        ]);
    }
}
