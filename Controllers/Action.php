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
 * Abstract Class, with some general controller-methods
 * 
 * - offers the default Zend_Session_Namespace in $this->session
 * - triggers the following Zend-Events for all controllers:
 *      - "beforeIndexAction" on preDispatch
 *      - "afterIndexAction" with parameter $this->view on postDispatch
 */


abstract class ZfExtended_Controllers_Action extends Zend_Controller_Action {
    /**
     * @var Zend_Session_Namespace
     */
    protected $_session = false;
    
    /**
     * @var ZfExtended_EventManager
     */
    protected $events = false;
    
    
    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array()) {
        parent::__construct($request, $response, $invokeArgs);
        $this->_helper = new ZfExtended_Zendoverwrites_Controller_Action_HelperBroker($this);
        $this->_session = new Zend_Session_Namespace();
        $this->events = ZfExtended_Factory::get('ZfExtended_EventManager', array(get_class($this)));
        $this->init();
    }
    
    public function init() {
        $this->view->controller = $this->_request->getControllerName();
        $this->view->action = $this->_request->getActionName();
    }
    
    /**
     * triggers event "before<Controllername>Action"
     */
    public function preDispatch()
    {
        $eventName = "before".ucfirst($this->_request->getActionName())."Action";
        $this->events->trigger($eventName, $this);
    }
    
    /**
     * triggers event "after<Controllername>Action"
     */
    public function postDispatch()
    {
        $eventName = "after".ucfirst($this->_request->getActionName())."Action";
        $this->events->trigger($eventName, $this, array($this->view));
    }
}

