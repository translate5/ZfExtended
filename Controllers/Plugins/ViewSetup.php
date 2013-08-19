<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

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
        $view->doctype('XHTML1_STRICT');
        $view->headMeta()->appendHttpEquiv('Content-Type',
                'text/html; charset=utf-8');
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
     /**
     * Stellt allgemeine php-Variablen in JS zur Verfügung
     *
     * @param Zend_Controller_Request_Abstract $request
     */
    private function setPhp2Js() {
        $this->_viewRenderer->view->headScript()->prependScript('var '.ucfirst($this->_viewRenderer->view->module).' = {}');
        $this->_viewRenderer->view->headScript()->appendScript($this->_viewRenderer->view->Php2JsVars());
        $this->_viewRenderer->view->php2JsVars()->set('pathToRunDir', APPLICATION_RUNDIR);
        $this->_viewRenderer->view->php2JsVars()->set('zfModule', $this->_viewRenderer->view->module);
        $this->_viewRenderer->view->php2JsVars()->set('zfController', $this->_viewRenderer->view->controller);
        $this->_viewRenderer->view->php2JsVars()->set('zfAction', $this->_viewRenderer->view->action);
    }
}
