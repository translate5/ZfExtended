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
 * abstract class of a log writer. Log writers are responsible to filter and write log events to their final destination
 */
abstract class ZfExtended_Logger_Writer_Abstract {
    
    /**
     * Configuration of the writer
     * @var array
     */
    protected $options;
    
    /**
     * creates a Logger writer as defined in the given options array, possible values 
     * @param array $options 
     * @return ZfExtended_Logger_Writer_Abstract
     */
    public static function create(array $options) {
        $cls = 'ZfExtended_Logger_Writer_'.$options['type'];
        if(!class_exists($cls)) {
            $cls = $options['type'];
        }
        if(class_exists($cls)) {
            return ZfExtended_Factory::get($cls, [$options]);
        }
        throw new ZfExtended_Logger_Exception("ZfExtended_Logger writer ".$options['type']." not found!");
    }
    
    public function __construct(array $options) {
        $this->validateOptions($options);
        $this->options = $options;
    }
    
    /**
     * Writes the given event to the log if event matches the configured filters  
     * @param ZfExtended_Logger_Event $event
     */
    abstract public function write(ZfExtended_Logger_Event $event);
    
    /**
     * returns true if writer accepts (via configured filters) the given event
     * @param ZfExtended_Logger_Event $event
     * @return boolean
     */
    public function isAccepted(ZfExtended_Logger_Event $event) {
        //FIXME implement basic filter here!
        return true;
    }
    
    /**
     * Validates the given options
     */
    abstract public function validateOptions(array $options); 
    
    /**
     * Converts data to JSON, uses the data object of entities, on JSON errors the error and the raw data is returned
     * @param mixed $data mostly an array
     * @return string
     */
    protected function toJson($data) {
        if(empty($data)) {
            return null;
        }
        $data = array_map(function($item) {
            if(is_object($item) && $item instanceof ZfExtended_Models_Entity_Abstract) {
                return $item->getDataObject();
            }
            return $item;
        }, (array) $data);
        $result = json_encode($data);
        if(empty($result) && json_last_error() > JSON_ERROR_NONE) {
            $result = 'JSON Error: '.json_last_error_msg().' ('.json_last_error().")\n";
            $result .= 'Raw Data: '.print_r($data, 1);
        }
        return $result;
    }
}