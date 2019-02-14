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

/**
 */
class ZfExtended_Logger_Writer_Database extends ZfExtended_Logger_Writer_Abstract {
    /**
     * @var Zend_Mail
     */
    protected $mail; 
    
    public function __construct(array $options) {
        parent::__construct($options);
    }
    
    public function write(ZfExtended_Logger_Event $event) {
        //FIXME wie implementiere ich die duplikat erkennung? URL kann nicht rein (wegen dem dc parameter). Extra sollte rein, 
        $db = ZfExtended_Factory::get('ZfExtended_Models_Db_ErrorLog');
        /* @var $db ZfExtended_Models_Db_ErrorLog */
        
        //get this data directly from the event:
        $directlyFromEvent = ['level', 'domain', 'worker', 'eventCode', 'message', 'appVersion', 'file', 'line', 'trace', 'httpHost', 'url', 'method', 'userLogin', 'userGuid'];
        $data = [];
        foreach($directlyFromEvent as $key) {
            $data[$key] = $event->$key;
        }
        $data['last'] = NOW_ISO;
        //FIXME json_encode may not produce an error!
        //flatten entities to their dataobjects
        if(!empty($event->extra)) {
            $extra = array_map(function($item) {
                if(is_object($item) && $item instanceof ZfExtended_Models_Entity_Abstract) {
                    return $item->getDataObject();
                }
                return $item;
            }, (array) $event->extra);
            $data['extra'] = json_encode($extra);
        }
        //$data['count'] = 0; FIXME how to make the duplication recognition?
        $db->insert($data);
    }
    
    public function validateOptions(array $options) {
        
    }
    
}