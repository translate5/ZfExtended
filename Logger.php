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
     * @var ZfExtended_Logger_Writer_Abstract[]
     */
    protected $writer = [];
    
    /**
     */
    public function __construct($options = null) {
        if(empty($options) || empty($options['writer'])) {
            $options[] = ['type' => 'ErrorLog', 'level' => self::LEVEL_WARN];
        }
        foreach($options['writer'] as $name => $writerConfig) {
            if($writerConfig instanceof Zend_Config) {
                $writerConfig = $writerConfig->toArray();
            }
            $this->addWriter($name, ZfExtended_Logger_Writer_Abstract::create($writerConfig));
        }
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
    
    /**
     * logs the given stuff
     * @param string $message unformatted message
     * @param string $code    event code / error code, optional, since for logging of debug output not necessary, only for errors
     * @param integer $level event level
     * @param array $extraData optional extra data / info to the event
     * @param array $writerNames optional, if given uses this writer(s) only (names as given in config)
     */
    protected function log($code, $message, $level = self::LEVEL_INFO, array $extraData = null, array $writerNames = null) {
        $event = new ZfExtended_Logger_Event();
        $event->created = NOW_ISO;
        
        $event->domain = $this->domain;
        $event->level = $level;
        $event->eventCode = $code;
        $event->message = $this->formatMessage($message, $extraData);
        $this->fillTrace($event);
        $event->extra = $extraData;
        
        $this->fillStaticData($event);
        $this->processEvent($event, is_null($writerNames) ? [] : $writerNames);
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
        
        $event->exception = $exception;
        $event->eventCode = $exception instanceof ZfExtended_ErrorCodeException ? 'E'.$exception->getCode() : $exception->getCode();
        $event->message = $this->formatMessage($exception->getMessage(), $extraData);
        $this->fillTrace($event, $exception);
        $event->extra = $extraData;
        
        $this->fillStaticData($event);
        $this->processEvent($event);
    }
    
    /**
     * pass the event to each configured writer, or to the given one only
     * @param ZfExtended_Logger_Event $event
     * @param string[] $writerName
     */
    protected function processEvent(ZfExtended_Logger_Event $event, array $writersToUse = []) {
        $availableWriters = array_keys($this->writer);
        if(!empty($writersToUse)) {
            $availableWriters = array_intersect($writersToUse, $availableWriters);
        }
        foreach($availableWriters as $name) {
            $writer = $this->writer[$name];
            $writer->isAccepted($event) && $writer->write($event);
        }
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
        }
        else {
            $trace = $e->getTrace();
            $event->trace = $e->getTraceAsString();
            $event->file = $e->getFile();
            $event->line = $e->getLine();
        }
        foreach($trace as $step) {
            if(empty($step['class'])){
                continue;
            }
            if(is_a($step['class'], 'ZfExtended_Worker_Abstract')) {
                $event->worker = $step['class'];
                break;
            }
        }
    }
    
    
    
    /**
     * Fills up log data about the request and the current user
     * @param ZfExtended_Logger_Event $event
     */
    protected function fillStaticData(ZfExtended_Logger_Event $event) {
        $event->levelName = $this->getLevelName($event->level);
        
        if(!empty($_SERVER['HTTP_HOST'])) {
            $event->httpHost = $_SERVER['HTTP_HOST'];
        }
        if(!empty($_SERVER['REQUEST_URI'])) {
            $event->url = $_SERVER['REQUEST_URI'];
        }
        if(!empty($_SERVER['REQUEST_METHOD'])) {
            $event->method = $_SERVER['REQUEST_METHOD'];
        }
        
        $event->appVersion = APPLICATION_VERSION;
        //FIXME add POST/PUT parameters as additional extraData! → obsolete? see separate request loggin?
        
        if(Zend_Session::isStarted()) {
            $user = new Zend_Session_Namespace('user');
            if(!empty($user->data->userGuid)){
                $event->userGuid = $user->data->userGuid;
                $event->userLogin = $user->data->login.' ('.$user->data->firstName.' '.$user->data->surName.')';
            }
        }
    }
    
    /**
     * replaces the {} placeholders in the message with data from the extra data array
     * can deal with {0} numeric placeholders and {key} assoc key placeholders
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
            if(is_object($item) && $item instanceof ZfExtended_Models_Entity_Abstract) {
                $item = $item->getDataObject();
            }
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
    
    /**
     * Adds a writer to this logger instance
     * @param string $name same named writers overwrite each other
     * @param ZfExtended_Logger_Writer_Abstract $writer
     */
    public function addWriter($name, ZfExtended_Logger_Writer_Abstract $writer) {
        $this->writer[$name] = $writer;
    }
    
    /**
     * returns the levelname to the given LEVEL_CONST integer
     * @param integer $level
     * @return string
     */
    public function getLevelName($level) {
        foreach($this->logLevels as $idx => $name) {
            if(($level & $idx) > 0) {
                return substr($name, 6);
            }
        }
        return null;
    }
    
    /**
     * Logs the current server request with all request data
     * @param array $additionalData
     */
    public function request(array $additionalData = []) {
        $additionalData['requestData'] = $_REQUEST;
        $this->debug('E1014', 'HTTP request '.$_SERVER['REQUEST_URI'], $additionalData);
    }
    
    /**
     * @param string $method
     * @param array $arguments
     * @throws InvalidArgumentException
     */
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
                $writer = [];
                break;
            case 3:
                $code = array_shift($arguments);
                $message = array_shift($arguments);
                $extra = array_shift($arguments);
                $writer = [];
                break;
            default:
                $code = array_shift($arguments);
                $message = array_shift($arguments);
                $extra = array_shift($arguments);
                $writer = array_shift($arguments);
                //do additional parameters!?
                break;
        }
        $this->log($code, $message, $level, $extra, $writer);
    }
}