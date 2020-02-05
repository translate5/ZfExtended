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

trait ZfExtended_Controllers_MaintenanceTrait{
    
    protected $enableMaintenanceHeader = true;
    
    public function displayMaintenance() {
        if($this->_response->isException()){
            return;
        }
        $config = Zend_Registry::get('config');
        if(!isset($config->runtimeOptions->maintenance)){
            return;
        }
        $directMaintenance = ZfExtended_Debug::hasLevel('core', 'maintenance');
        $maintenanceStartDate=$config->runtimeOptions->maintenance->startDate;
        
        if(!$directMaintenance && (!$maintenanceStartDate || !(strtotime($maintenanceStartDate)<= (time()+ 86400)))){//if there is no date and the start date is not in the next 24H
            return;
        }
        
        //since database maintenance is also part of maintenance, its controller should be able to run
        if($this->_request->getControllerName() === 'database') {
            return;
        }
        
        if($directMaintenance || new DateTime() >= new DateTime($maintenanceStartDate)){
            throw new ZfExtended_Models_MaintenanceException();
        }
        $maintenanceTimeToNotify= max(1, (int) $config->runtimeOptions->maintenance->timeToNotify);
        $maintenanceMessage = $config->runtimeOptions->maintenance->message ?? '';
     
        $maintenanceStartDate = strtotime($maintenanceStartDate);
        $time = $maintenanceStartDate - ($maintenanceTimeToNotify * 60);
        
        $date = new DateTime(date("Y-m-d H:i:s", $time));
        
        if(new DateTime() < $date ){
            return;
        }
        
        if($this->enableMaintenanceHeader) {
            $this->_response->setHeader('x-translate5-shownotice', date(DATE_ISO8601, $maintenanceStartDate));
            if(!empty($maintenanceMessage)) {
                $this->_response->setHeader('x-translate5-maintenance-message', $maintenanceMessage);
            }
        }
        $this->view->displayMaintenancePanel = true;
    }

    /***
     * Locks the login (configurable minutes) before the mainteance mode
     * @return boolean
     */
    protected function isMaintenanceLoginLock(){
        /* @var $config Zend_Config */
        $config = Zend_Registry::get('config');
        $rop = $config->runtimeOptions;
        if(!isset($rop->maintenance)){
            return;
        }
        
        $maintenanceStartDate=$rop->maintenance->startDate;
        if(!$maintenanceStartDate || !(strtotime($maintenanceStartDate)<= (time()+ 86400))){//if there is no date and the start date is not in the next 24H
            return false;
        }
    
        $timeToLoginLock = max(1, (int) $rop->maintenance->timeToLoginLock);
        
        $time = strtotime($maintenanceStartDate);
        $time = $time - ($timeToLoginLock * 60);
        $date = date("Y-m-d H:i:s", $time);
    
        if(new DateTime() >= new DateTime($date)){
            if($this instanceof ZfExtended_RestController || empty($this->_form)) {
                throw new ZfExtended_Models_MaintenanceException('Maintenance scheduled in a few minutes: '.$date);                
            }
            $this->_form->addError($this->_translate->_("Eine Wartung steht unmittelbar bevor, Sie können sich daher nicht anmelden. Bitte versuchen Sie es in Kürze erneut."));
            $this->view->form = $this->_form;
            return true;
        }
    }
}