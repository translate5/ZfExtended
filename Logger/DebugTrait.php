<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
 * trait to provide reusable debug and trace function, which can remain in the code, 
 * but for performance reasons are only executed if a writer is configured to write to them 
 */
trait ZfExtended_Logger_DebugTrait {
    /**
     * Reusable logger instance, preconfigured for the current domain
     * @var ZfExtended_Logger
     */
    protected $log;
    
    protected $_logEnableDebug = false;
    protected $_logDebugECode = 'E0000';
    protected $_logDebugPrefix = '';
    
    /**
     * Must be called in order to configure / prepare the debug logger
     * @param string $debugECode ECode EXXXX which should be used for the debug/trace messages
     * @param string $loggerDomain the domain to be used for the log messages
     * @param string $enabledForDomain optional, defaults to $loggerDomain. If $loggerDomain is more specific (plugin.foo.connector), the enabled for domain is the more general (plugin.foo) 
     * @param string $debugPrefix optional, defaults to empty string
     */
    protected function initLogger(string $debugECode, string $loggerDomain, string $enabledForDomain = '', string $debugPrefix = '') {
        if(empty($enabledForDomain)) {
            $enabledForDomain = $loggerDomain;
        }
        $this->log = Zend_Registry::get('logger')->cloneMe($loggerDomain);
        $this->_logEnableDebug = $this->log->isEnabledFor($this->log::LEVEL_DEBUG, $enabledForDomain);
    }
    
    /**
     * Debugging method, enabled if a writer is configured to listen to debug messages of this domain
     * @param string $msg
     * @param array $data
     */
    protected function debug(string $msg, array $data = []) {
        $this->_doDebug(__FUNCTION__, $msg, $data);
    }
    
    /**
     * Debugging method, enabled if a writer is configured to listen to debug messages of this domain
     * @param string $msg
     * @param array $data
     */
    protected function trace(string $msg, array $data = []) {
        $this->_doDebug(__FUNCTION__, $msg, $data);
    }
    
    /**
     * performs the concrete debug trace function
     * @param string $method
     * @param string $msg
     * @param array $data
     */
    private function _doDebug(string $method, string $msg, array $data = []) {
        if(!$this->_logEnableDebug) {
            return;
        }
        $this->log->trace($this->_logDebugECode, $this->_logDebugPrefix.$msg, $data);
    }
}