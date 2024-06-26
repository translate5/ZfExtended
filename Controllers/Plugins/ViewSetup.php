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
 * Plugin, das den View mit Basiseigenschaften initialisiert
 */
class ZfExtended_Controllers_Plugins_ViewSetup extends Zend_Controller_Plugin_Abstract
{
    /**
     * @var Zend_Controller_Action_Helper_ViewRenderer
     */
    protected $_viewRenderer;

    /**
     * @var Zend_Session_Namespace
     */
    protected $_session;
    /**
     * @var Zend_Session_Namespace
     */

    /**
     * Stellt allgemeine Viewkomponenten zur Verfügung
     *
     * - richtet den view ein
     * - entscheidet, welche Elemente im Head zu finden sind
     * - setzt die basePaths
     * - fügt action, controller, module, session, cache und layout dem view als Variablen hinzu
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        $this->_session = new Zend_Session_Namespace();
        $this->_viewRenderer = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'ViewRenderer'
        );
        $view = new Zend_View();
        $config = Zend_Registry::get('config');
        $libs = array_reverse($config->runtimeOptions->libraries->order->toArray());
        foreach ($libs as $lib) {
            $view->addBasePath(APPLICATION_PATH . '/../library/' . $lib . '/views/', $lib . '_View_');
        }
        $view->addBasePath(APPLICATION_PATH . '/modules/' . Zend_Registry::get('module') . '/views/', 'View_');
        $view->addBasePath(APPLICATION_PATH . '/../client-specific/views/' . Zend_Registry::get('module') . '/', 'View_');

        $view->doctype('XHTML1_STRICT');
        $view->headMeta()->appendHttpEquiv('Content-Type', 'text/html; charset=utf-8');
        $view->layout = Zend_Layout::getMvcInstance();
        $view->cache = Zend_Registry::get('cache');
        $view->session = $this->_session;
        $view->module = Zend_Registry::get('module');
        $view->action = $request->getActionName();
        $view->controller = $request->getControllerName();
        $this->_viewRenderer->setView($view);
        $this->_viewRenderer->setNoRender(true);
        $this->setPhp2Js();
    }

    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        $this->_viewRenderer = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'ViewRenderer'
        );
        $this->_viewRenderer->view->translate = ZfExtended_Zendoverwrites_Translate::getInstance();

        //setting the locale is must be done dispatchLoopStartup
        $view = $this->_viewRenderer->view;
        $view->php2JsVars()->set('locale', $this->_session->locale);
    }

    /**
     * Stellt allgemeine php-Variablen in JS zur Verfügung
     */
    private function setPhp2Js()
    {
        $view = $this->_viewRenderer->view;
        $view->headScript()->appendScript($this->_viewRenderer->view->Php2JsVars());
        $view->php2JsVars()->set('pathToRunDir', APPLICATION_RUNDIR);
        $view->php2JsVars()->set('zfModule', $this->_viewRenderer->view->module);
        $view->php2JsVars()->set('zfController', $this->_viewRenderer->view->controller);
        $view->php2JsVars()->set('zfAction', $this->_viewRenderer->view->action);
    }
}
