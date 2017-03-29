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
 * Aktiviert bei aktiviertem errorCollect (siehe runtimeOptions.errorCollect = 0 in der application.ini)
 * den ErrorcollectController
 * 
 * - funktioniert nur gemeinsam mit ZfExtended_Resource_ErrorHandler
 *
 */
class ZfExtended_Controllers_Plugins_ErrorCollect extends Zend_Controller_Plugin_Abstract
{
    /**
     * Wird vor der Dispatcher Schleife aufgerufen
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function  postDispatch(Zend_Controller_Request_Abstract $request) {
        parent::postDispatch($request);
        $errorCollect = Zend_Registry::get('errorCollect');
        if($errorCollect){
            $errorCollector = Zend_Registry::get('errorCollector');
            if(count($errorCollector)>0 and $request->getControllerName()!='error'){
                $request->setControllerName('error');
                $request->setActionName('error');
                $request->setDispatched(false);
            }
        }
    }
}
