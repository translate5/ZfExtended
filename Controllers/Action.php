<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
abstract class ZfExtended_Controllers_Action extends Zend_Controller_Action {
    use ZfExtended_Controllers_MaintenanceTrait;
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
        $this->displayMaintenance();
        $eventName = "before".ucfirst($this->_request->getActionName())."Action";
        $this->events->trigger($eventName, $this);
        
        
        
    }
    
    /**
     * triggers event "after<Controllername>Action"
     */
    public function postDispatch()
    {
        $eventName = "after".ucfirst($this->_request->getActionName())."Action";
        $this->events->trigger($eventName, $this, array('view' => $this->view));
    }
    
    /**
     * returns the deployed version number, or "dev" if no version file exists.
     * "invalid" is returned if version file contains invalid content.
     * @return string
     */
    protected function getAppVersion() {
        $versionFile = APPLICATION_PATH.'/../version';
        if(!file_exists($versionFile)) {
            return 'dev';
        }
        $version = file_get_contents($versionFile);
        preg_match('/MAJOR_VER=([0-9]+).+MINOR_VER=([0-9]+).+BUILD=([0-9]+).+/s', $version, $match);
        if(empty($match) || count($match) != 4) {
            return 'invalid';
        }
        return $match[1].'.'.$match[2].'.'.$match[3];
    }
}