<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

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
 *
 */
class ZfExtended_Controllers_Plugins_ViewSetup extends Zend_Controller_Plugin_Abstract
{
    /**
     * @var Zend_Controller_Action_Helper_ViewRenderer
     */
    protected  $_viewRenderer;
    
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
            $view->addBasePath(APPLICATION_PATH.'/../library/'.$lib.'/views/', $lib.'_View_');
        }
        $view->addBasePath(APPLICATION_PATH.'/modules/'.Zend_Registry::get('module').'/views/', 'View_');
        $view->addBasePath(APPLICATION_PATH.'/../client-specific/views/'.Zend_Registry::get('module').'/', 'View_');
        
        $view->doctype('XHTML1_STRICT');
        $view->headMeta()->appendHttpEquiv('Content-Type', 'text/html; charset=utf-8');
        $view->layout = Zend_Layout::getMvcInstance();
        $view->cache = Zend_Registry::get('cache');
        $view->session = $this->_session;
        $view->module = Zend_Registry::get('module');
        $view->action = Zend_Registry::get('action');
        $view->controller = Zend_Registry::get('controller');
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
        
        
        $config = Zend_Registry::get('config');
        $rop = $config->runtimeOptions;
        //maintenance start date
        $view->Php2JsVars()->set('mntStartDate',$rop->mntStartDate);
        //maintenance warning panel is showed
        $view->Php2JsVars()->set('mntCountdown',$rop->mntCountdown);
        //minutes before the point in time of the update the application is locked for new log-ins
        $view->Php2JsVars()->set('mntLoginBlock',$rop->mntLoginBlock);
    }
    
     /**
     * Stellt allgemeine php-Variablen in JS zur Verfügung
     *
     * @param Zend_Controller_Request_Abstract $request
     */
    private function setPhp2Js() {
        $view = $this->_viewRenderer->view;
        $view->headScript()->prependScript('var '.ucfirst($this->_viewRenderer->view->module).' = {}');
        $view->headScript()->appendScript($this->_viewRenderer->view->Php2JsVars());
        $view->php2JsVars()->set('pathToRunDir', APPLICATION_RUNDIR);
        $view->php2JsVars()->set('zfModule', $this->_viewRenderer->view->module);
        $view->php2JsVars()->set('zfController', $this->_viewRenderer->view->controller);
        $view->php2JsVars()->set('zfAction', $this->_viewRenderer->view->action);
    }
}
