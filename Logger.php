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
 * design facts:
 * - Inside the logger no translation is needed, since logging and transporting messages to the customer are two different parts
 * - since we are not only logging errors but also warnings, infos etc, we are talking about events not errors to be logged.
 *
 * @method void fatal() fatal(string $code, string $message)
 * @method void error() error(string $code, string $message)
 * @method void warn() warn(string $code, string $message)
 * @method void info() info(string $code, string $message)
 * @method void debug() debug(string $code, string $message)
 * @method void trace() trace(string $code, string $message)
 */
class ZfExtended_Logger {
    /**
     * Defining the log levels (draft, not really used at the moment)
     * Using 2^n values for better filtering and combining possibilties, although a simple < comparsion should be enough 
     * @var integer
     */
    const LEVEL_FATAL = 1;
    const LEVEL_ERROR = 2;
    const LEVEL_WARN = 4;
    const LEVEL_INFO = 8;
    const LEVEL_DEBUG = 16;
    const LEVEL_TRACE = 32;
    
    protected $logLevels = [];
    
    protected $domain = 'core';
    
    /**
     */
    public function __construct($options = null) {
        //FIXME evaluate $options to setup logger!
        $r = new ReflectionClass($this);
        $this->logLevels = array_flip($r->getConstants());
    }
    
    /*
    id 	int, auto_inc. 	automatically
    created 	date, first occurence 	automatically
    last 	date, last occurence (for multiple in a specific time window) 	by ErrorLogger
    count 	count how often the error happened between "created" and "last" 	automatically
    level 	fatal to trace, see TRANSLATE-76 	from exception default
    domain 	error area, for further filtering, can be for example: import / export / Plugin XYZ, and so on 	from exception type
    worker 	worker class (loop over debug_backtrace, and ue is_subclass_of) 	automatically
    errorCode 	string, the project unique error code 	from exception
    error 	string, error textual description 	from exception
    file 	This info should be kept for each error 	from exception
    line 	This info should be kept for each error 	from exception
    trace 	Only the "small" exception trace, only if enabled in the exception, for the most exceptions the file and line is sufficient 	from exception
    url 	called URL 	from request
    method 	HTTP method 	from request
    user 	the authenticated user 	from session
    taskGuid 	affected task 	from exception
    */
    
    protected function log($code, $message, $level = self::LEVEL_INFO, array $extraData = null) {
        $event = new ZfExtended_Logger_Event();
        $event->created = NOW_ISO;
        
        $event->domain = $this->domain;
        $event->level = $level;
        $event->eventCode = $code;
        $event->message = $this->formatMessage($message, $extraData);
        $this->fillTrace($event);
        $event->extra = $extraData;
        
        $this->fillStaticData($event);
        $this->processEvent($event);
    }
    
    public function exception(Exception $exception) {
        $event = new ZfExtended_Logger_Event();
        $event->created = NOW_ISO;
        
        if($exception instanceof ZfExtended_Exception){
            $event->level = $exception->getLevel();
            $extraData = $exception->getErrors();
            $event->domain = $exception->getOrigin();
        }
        else {
            //exceptions not defined and not catched by us are of type error 
            $event->level = self::LEVEL_ERROR;
            $extraData = [];
            $event->domain = $this->domain;
        }
        
        $event->eventCode = $exception->getCode();
        $event->message = $this->formatMessage($exception->getMessage(), $extraData);
        $this->fillTrace($event, $exception);
        $event->extra = $extraData;
        
        $this->fillStaticData($event);
        $this->processEvent($event);
    }
    
    protected function processEvent(ZfExtended_Logger_Event $event) {
        error_log($event);
    }
    
    //FIXME fillTrace only if needed, depending on the LEVEL!
    protected function fillTrace(ZfExtended_Logger_Event $event, Exception $e = null) {
        if(empty($e)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $stepBefore = [];
            while($step = array_shift($trace)) {
                if(empty($step['class']) || $step['class'] !== 'ZfExtended_Logger') {
                    break;
                }
                $stepBefore = $step;
            }
            settype($stepBefore['file'], 'string');
            settype($stepBefore['line'], 'string');
            $event->file = $stepBefore['file'];
            $event->line = $stepBefore['line'];
            return;
        }
        $event->trace = $e->getTraceAsString();
        $event->file = $e->getFile();
        $event->line = $e->getLine();
    }
    
    /**
     * Fills up log data about the request and the current user
     * @param ZfExtended_Logger_Event $event
     */
    protected function fillStaticData(ZfExtended_Logger_Event $event) {
        if(!empty($_SERVER['REQUEST_URI'])) {
            $event->url = $_SERVER['REQUEST_URI'];
        }
        if(!empty($_SERVER['REQUEST_METHOD'])) {
            $event->method = $_SERVER['REQUEST_METHOD'];
        }
        
        //FIXME add POST/PUT parameters as additional extraData!
        
        if(Zend_Session::isStarted()) {
            $user = new Zend_Session_Namespace('user');
            $event->userGuid = $user->data->userGuid;
            $event->userLogin = $user->data->login;
        }
    }
    
    /**
     * replaces the {} placeholders in the message with data from the extra data array
     * can deal with {0} numeric placeholders an {key} assoc key placeholders
     * @param string $message
     * @param array $extra
     * @return string
     */
    protected function formatMessage($message, array $extra = null){
        if(empty($extra)) {
            return $message;
        }
        $keys = array_keys($extra);
        $data = array_values($extra);
        $numericKeys = array_keys($data);
        $toPlaceholder = function($item) {
            return '{'.$item.'}';
        };
        $keys = array_map($toPlaceholder, $keys);
        $numericKeys = array_map($toPlaceholder, $numericKeys);
        
        //flatten data to strings
        $data = array_map(function($item) {
            if(is_array($item) || is_object($item) && !method_exists($item, '__toString')) {
                return print_r($item, 1);
            }
            return (string) $item;
        }, $data);
        
        //replace numeric placeholders
        $message = str_replace($numericKeys, $data, $message);
        //replace assoc key placeholders
        return str_replace($keys, $data, $message);
    }
    
    public function __call($method, $arguments) {
        $level = 'LEVEL_'.strtoupper($method);
        if (($level = array_search($level, $this->logLevels)) === false) {
            $level = self::LEVEL_INFO; //default level on invalid level given
        }
        switch (count($arguments)) {
            case 0:
            case 1:
                throw new InvalidArgumentException('Missing Arguments $code and $message');
            case 2:
                $code = array_shift($arguments);
                $message = array_shift($arguments);
                $extra = null;
                break;
            default:
                $code = array_shift($arguments);
                $message = array_shift($arguments);
                $extra = array_shift($arguments);
                //do additional parameters!
        }
        $this->log($code, $message, $level, $extra);
    }
}