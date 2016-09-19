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

trait ZfExtended_Controllers_MaintenanceTrait{
    
    public function displayMaintenance() {
    	if($this->_response->isException()){
    		return;
    	}
    	$config = Zend_Registry::get('config');
    	$maintenanceStartDate=$config->runtimeOptions->maintenance->startDate;
    	//$mntStartDate = time() + 30;
    	
    	if(!$maintenanceStartDate || !(strtotime($maintenanceStartDate)<= (time()+ 86400))){//if there is no date and the start date is not in the next 24H
    		return;
    	}
    	
    	if(new DateTime() >=  new DateTime($maintenanceStartDate)){
    		throw new ZfExtended_Models_MaintenanceException();
    	}
    	$maintenanceTimeToNotify=$config->runtimeOptions->maintenance->timeToNotify;
    	 
    	$time = strtotime($maintenanceStartDate);
    	$time = $time - ($maintenanceTimeToNotify * 60);
    	$date = new DateTime(date("Y-m-d H:i:s", $time));
    	
    	
    	if(new DateTime() >= $date ){
    		$this->_response->setHeader('x-translate5-shownotice', $maintenanceStartDate);
    	}
    }

    /***
     * Locks the login (configurable minutes) before the mainteance mode
     * @return boolean
     */
    private function isMaintenanceLoginLock(){
        /* @var $config Zend_Config */
        $config = Zend_Registry::get('config');
        $rop = $config->runtimeOptions;
    
        $maintenanceStartDate=$rop->maintenance->startDate;
        if(!$maintenanceStartDate || !(strtotime($maintenanceStartDate)<= (time()+ 86400))){//if there is no date and the start date is not in the next 24H
            return false;
        }
    
        $time = strtotime($maintenanceStartDate);
        $time = $time - ($rop->maintenance->timeToLoginLock * 60);
        $date = new DateTime(date("Y-m-d H:i:s", $time));
    
        if(new DateTime() >= $date ){
            $this->_form->addErrorMessage("Maintenance mode !");
            $this->view->form = $this->_form;
            return true;
        }
    }
}