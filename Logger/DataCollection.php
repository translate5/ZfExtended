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
 * convenient collection of multiple data sets to be logged to one single common error
 * 
 * @method void fatal() fatal(string $code, $extra = null)
 * @method void error() error(string $code, $extra = null)
 * @method void warn() warn  (string $code, $extra = null)
 * @method void info() info  (string $code, $extra = null)
 * @method void debug() debug(string $code, $extra = null)
 * @method void trace() trace(string $code, $extra = null)
 */
class ZfExtended_Logger_DataCollection {
    /**
     * The collected and grouped errors
     * @var array
     */
    protected $data = [];
    
    /**
     * Domain to be used
     * @var string
     */
    protected $domain;
    
    /**
     * available mesages for that logged data collection
     * @var array
     */
    protected $messages;
    
    public function __construct(string $domain, array $messages) {
        $this->domain = $domain;
        $this->messages = $messages;
        foreach(array_keys($messages) as $ecode) {
            $this->data[$ecode] = [];
        }
    }
    
    /**
     * adds log data to an given E-Code
     * @param string $groupEcode
     * @param array $data
     */
    public function add(string $groupEcode, array $data) {
        if(!array_key_exists($groupEcode, $this->messages)) {
            throw new ZfExtended_Logger_Exception('Invalid Ecode '.$groupEcode.' used in ZfExtended_Logger_DataCollection instance. Valid are '.print_r($this->message, 1));
        }
        $this->data[$groupEcode][] = $data;
    }
    
    public function __call(string $name, array $args) {
        $code = $args[0] ?? 'E9999';
        if(empty($this->data[$code])) {
            return;
        }
        $log = Zend_Registry::get('logger')->cloneMe($this->domain);
        /* @var $log ZfExtended_Logger */
        $data = array_merge($this->data[$code] ?? [], $args[1] ?? []);
        $log->__call($name, [$code, $this->messages[$code] ?? 'Unknown Error', $data]);
    }
}