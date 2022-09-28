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
 * Eigener Errorhandler
 *
 * - Wirft für alle abfangbaren Fehler eine Zend_Exception
 * - E_USER_NOTICE wird nicht als Fehler gewertet, aber an allen relevanten Stellen 
 *   mit geloggt. D. h., einziger Loggingunterschied ist, dass der Fehler- und 
 *   http-Responsecode nicht auf 500 sondern auf 200 steht
 *
 *
 */
class ZfExtended_Resource_ErrorHandler extends Zend_Application_Resource_ResourceAbstract {
    
    /**
     * Mapping of error codes to there speakable name, and the level how it should be logged in the application
     * @var array
     */
    protected $errorCodes = [
        1 => ['E_ERROR', 'fatal'],                 //FATAL
        2 => ['E_WARNING', 'info'],                //info
        4 => ['E_PARSE', 'fatal'],                 //FATAL
        8 => ['E_NOTICE', 'info'],                 //info
        16 => ['E_CORE_ERROR', 'fatal'],           //FATAL
        32 => ['E_CORE_WARNING', 'info'],          //info
        64 => ['E_COMPILE_ERROR', 'fatal'],        //FATAL
        128 => ['E_COMPILE_WARNING', 'warn'],      //warn
        256 => ['E_USER_ERROR', 'fatal'],          //FATAL → should be an exception
        512 => ['E_USER_WARNING', 'info'],         //info
        1024 => ['E_USER_NOTICE', 'info'],         //info
        2048 => ['E_STRICT', 'debug'],             //debug
        4096 => ['E_RECOVERABLE_ERROR', 'fatal'],  //FATAL
        8192 => ['E_DEPRECATED', 'debug'],         //debug
        16384 => ['E_USER_DEPRECATED', 'debug'],   //debug
    ];
    
    public function init()
    {
        register_shutdown_function(array($this, 'handleFatalError'));
    }

    /**
     * @throws Zend_Exception
     */
    public function handleFatalError() {
        $error = error_get_last();
        if(empty($error)) {
            return;
        }
        $type = empty($error['type']) ? E_ERROR : $error['type'];
        if(($type & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) === 0) {
            //we may catch here only the above listed errors, since they stop execution. 
            // all warnings and notices are not logged here, since they are logged automatically in the error_log, 
            // so logging them here would log them twice. Also all errors / warnings protected with @ are logged then again
            return;
        }
        
        $label = $this->errorCodes[$type][0];
        $level = $this->errorCodes[$type][1];
        $msg = 'PHP '.$label.': ';

        //in early bootstrapping logger is maybe not defined yet
        if(Zend_Registry::isRegistered('logger')) {
            $codes = ['fatal' => 'E1027', 'warn' => 'E1029', 'info' => 'E1030', 'debug' => 'E1030'];
            $logger = Zend_Registry::get('logger');
            /* @var $logger ZfExtended_Logger */
            $logger->finalError($codes[$level], $msg, $level, $error);
        }

        //on fatal errors we assume that there is no usable out put, so we overwrite it
        if($level != 'fatal') {
            return;
        }
        if(!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }
        if(PHP_SAPI === 'cli') {
            echo ob_get_clean();
            return;
        }
        $out = '';
        ob_get_length() && ob_clean(); //show only a white page
        echo '<h1>Internal Server Error</h1>'."\n".$out;
    }
    
    /**
     * returns a backtrace as string
     * @return string  
     */
    public static function getTrace(){
        $e = new Zend_Exception();
        return $e->getTraceAsString();
    }
}