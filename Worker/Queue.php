<?php
 /*
 START LICENSE AND COPYRIGHT
 
 This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
 
 Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

 This file may be used under the terms of the GNU General Public License version 3.0
 as published by the Free Software Foundation and appearing in the file gpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU General Public License version 3.0 requirements will be met:
 http://www.gnu.org/copyleft/gpl.html.

 For this file you are allowed to make use of the same FLOSS exceptions to the GNU 
 General Public License version 3.0 as specified by Sencha for Ext Js. 
 Please be aware, that Marc Mittag / MittagQI take no warranty  for any legal issue, 
 that may arise, if you use these FLOSS exceptions and recommend  to stick to GPL 3. 
 For further information regarding this topic please see the attached license.txt
 of this software package.
 
 MittagQI would be open to release translate5 under EPL or LGPL also, if this could be
 brought in accordance with the ExtJs license scheme. You are welcome to support us
 with legal support, if you are interested in this.
 
 
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU General Public License version 3.0 http://www.gnu.org/copyleft/gpl.html
             with FLOSS exceptions (see floss-exception.txt and ux-exception.txt at the root level)
 
 END LICENSE AND COPYRIGHT 
 */

class ZfExtended_Worker_Queue {
    
    public function process($taskGuid = NULL) {
        error_log(__CLASS__.' -> '.__FUNCTION__);
        
        $workerModel = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        /* @var $workerModel ZfExtended_Models_Worker */
        $workerListQueued = $workerModel->getListQueued();
        //$workerListQueued = $workerModel->getListQueued('{10ea5327-8257-4f4e-abf0-8063e9878b17}');
    
        foreach ($workerListQueued as $workerQueue) {
            
            $workerModel->init($workerQueue);
            // TODO start workerQueue by calling the REST-Controller
            // something like:
            $url = '/worker/'.$workerModel->getId().'/'.$workerModel->getHash();
            error_log(__CLASS__.' -> '.__FUNCTION__.'; start worker: '.$url);
            
            // $ch = curl_init($url);
            // curl_exec($ch);
            // curl_close($ch);
            
            continue;
            
            
            
            
            
            $worker = ZfExtended_Worker_Abstract::instanceByModel($workerModel);
            /* @var $worker editor_Worker_TermTagger */
    
            if (!$worker) {
                error_log(__CLASS__.' -> '.__FUNCTION__.' Worker could not be instanciated');
                return false;
            }
            //$worker->runQueued();
            //$result = $worker->getResult();
            error_log(__CLASS__.' -> '.__FUNCTION__.': '.print_r($result, true));
        }
        
    }
    
}