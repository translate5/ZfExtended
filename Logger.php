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
 * - since we are not only logging errors but also warnings, infos etc, we are talking about events not errors to be logged.
 *
 * @method void fatal() fatal(string $code, string $message, $extra = null, $writer = [])
 * @method void error() error(string $code, string $message, $extra = null, $writer = [])
 * @method void warn() warn  (string $code, string $message, $extra = null, $writer = [])
 * @method void info() info  (string $code, string $message, $extra = null, $writer = [])
 * @method void debug() debug(string $code, string $message, $extra = null, $writer = []) is only processed if filters are configured to do so (match domain and level)
 * @method void trace() trace(string $code, string $message, $extra = null, $writer = []) is only processed if filters are configured to do so (match domain and level)
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
    //DEBUG and TRACE log calls are only processed if there is on writer configured with a filter consuming debug logs of the current domain
    const LEVEL_DEBUG = 16;
    const LEVEL_TRACE = 32;
    
    const ECODE_LEGACY_ERRORS = 'E9999';
    
    protected $logLevels = [];
    
    protected $domain = 'core';
    
    /**
     * @var ZfExtended_Logger_Writer_Abstract[]
     */
    protected $writer = [];
    
    /**
     * before a trace is created, the current events level is compared on bit level against this value,
     *  if it does match, then the trace is created
     *  This value can be overridden via config
     * @var integer
     */
    protected $enableTraceFor = 51; // 1 + 2 + 16 + 32
    
    /**
     * Config options - mostly given by configuration in ini
     * @param array $options
     */
    public function __construct($options = null) {
        if(empty($options) || empty($options['writer'])) {
            $options[] = ['type' => 'ErrorLog', 'level' => self::LEVEL_WARN];
        }
        if(array_key_exists('enableTraceFor', $options)) {
            $this->enableTraceFor = (int) $options['enableTraceFor'];
        }
        foreach($options['writer'] as $name => $writerConfig) {
            //disable writer if config set to null / empty
            if(empty($writerConfig)) {
                continue;
            }
            if($writerConfig instanceof Zend_Config) {
                $writerConfig = $writerConfig->toArray();
            }
            $this->addWriter($name, ZfExtended_Logger_Writer_Abstract::create($writerConfig));
        }
        $r = new ReflectionClass($this);
        $this->logLevels = array_flip($r->getConstants());
    }
    
    /**
     * Clones the logger instance and resets the domain in the returned instance.
     * The purpose of this is to provide a convenience way to set a separate domain for multiple log calls
     * @param string $domain
     * @return ZfExtended_Logger
     */
    public function cloneMe($domain) {
        $clone = clone $this;
        $clone->domain = $domain;
        return $clone;
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
     * @param int $level event level
     * @param array $extraData optional extra data / info to the event
     * @param array $writerNames optional, if given uses this writer(s) only (names as given in config)
     */
    protected function log($code, $message, $level = self::LEVEL_INFO, array $extraData = null, array $writerNames = null) {
        $event = $this->prepareEvent($level, $code, $extraData);
        $event->message = $this->formatMessage($message, $extraData);
        $this->processEvent($event, is_null($writerNames) ? [] : $writerNames);
    }
    
    /**
     * The error handler for finally caught PHP errors
     * @param string $code
     * @param string $message
     * @param int $level
     * @param array $error
     */
    public function finalError($code, $message, $level, array $error) {
        $level = $this->levelFromString($level);
        
        settype($error['type'], 'integer');
        settype($error['message'], 'string');
        settype($error['file'], 'string');
        settype($error['line'], 'integer');
        
        $file = $error['file'];
        $line = $error['line'];
        $message .= $error['message'];
        //delete the standard infos from extraData, since they are stored in event directly
        unset($error['file']);
        unset($error['line']);
        unset($error['message']);
        unset($error['type']); //processed outside
        $event = $this->prepareEvent($level, $code, $error);
        $tracePos = strpos($message, "Stack trace:\n#0");
        if($tracePos === false) {
            $event->message = $message;
        } else {
            $event->message = substr($message, 0, $tracePos);
            $event->trace = substr($message, $tracePos);
        }
        $event->file = $file;
        $event->line = $line;
        $this->processEvent($event);
    }
    
    /**
     * Prepares the event instance
     * @param int $level
     * @param string $code
     * @param array $extraData or null
     * @return ZfExtended_Logger_Event
     */
    protected function prepareEvent($level, $code, $extraData) {
        $event = new ZfExtended_Logger_Event();
        $event->created = NOW_ISO;
        
        $event->domain = $this->domain;
        $event->level = $level;
        $event->eventCode = $code;
        $this->fillTrace($event);
        $event->extra = $extraData;
        
        $this->fillStaticData($event);
        $event->levelName = $this->getLevelName($event->level);
        return $event;
    }
    
    /**
     * Log the given exception
     * @param Exception $exception
     * @param array $eventOverride array to override the event generated from the exception
     * @param boolean $returnEvent if true given: return the created event instead processing it
     * @return ZfExtended_Logger_Event|null
     */
    public function exception(\Throwable $exception, array $eventOverride = [], $returnEvent = false): ?ZfExtended_Logger_Event {
        $event = new ZfExtended_Logger_Event();
        $event->created = NOW_ISO;
        
        if($exception instanceof ZfExtended_Exception){
            $event->level = $exception->getLevel();
            $extraData = $exception->getErrors();
            $event->domain = $exception->getDomain();
        }
        else {
            //exceptions not defined and not catched by us are of type error
            $event->level = self::LEVEL_ERROR;
            $extraData = [];
            $event->domain = $this->domain;
        }
        
        $event->exception = $exception;
        $event->eventCode = $exception instanceof ZfExtended_ErrorCodeException ? 'E'.$exception->getCode() : self::ECODE_LEGACY_ERRORS;
        $event->message = $this->formatMessage($exception->getMessage(), $extraData);
        $this->fillTrace($event, $exception);
        $event->extra = $extraData;
        
        $this->fillStaticData($event);
        $event->mergeFromArray($eventOverride);
        $event->levelName = $this->getLevelName($event->level);
        $previous = $exception->getPrevious();
        if(!empty($previous)) {
            $event->previous = $this->exception($previous, [], true);
        }
        if($returnEvent) {
            return $event;
        }
        $this->processEvent($event);
        return null;
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
    
    /**
     * test if current logger has writers consuming the given combination of level and domain.
     * Can be used for example in plug-in init Methods to enable the processing of debug statements in the plugin.
     * @param integer $level
     * @param string $domain
     */
    public function isEnabledFor(int $level, string $domain) {
        foreach($this->writer as $writer) {
            if($writer->isAcceptingBasicly($level, $domain)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * The trace information is set if
     * @param ZfExtended_Logger_Event $event
     * @param Exception $e
     */
    protected function fillTrace(ZfExtended_Logger_Event $event, \Throwable $e = null) {
        if(($this->enableTraceFor & $event->level) == 0) {
            return;
        }
        if(empty($e)) {
            $this->generateTrace($event);
        }
        else {
            $trace = $e->getTrace();
            $event->trace = $e->getTraceAsString();
            $event->file = $e->getFile();
            $event->line = $e->getLine();
            $this->fillWorker($event, $trace);
        }
    }
    
    /**
     * If we don't have an exception, we have to fill the trace from debug_backtrace
     * @param ZfExtended_Logger_Event $event
     */
    protected function generateTrace(ZfExtended_Logger_Event $event) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $stepBefore = [];
        $i = 0;
        while($step = array_shift($trace)) {
            if(empty($step['class']) || $step['class'] !== 'ZfExtended_Logger') {
                break;
            }
            $i++;
            $stepBefore = $step;
        }
        $this->fillWorker($event, $trace);
        settype($stepBefore['file'], 'string');
        settype($stepBefore['line'], 'string');
        $event->file = $stepBefore['file'];
        $event->line = $stepBefore['line'];
        if(($event->level & self::LEVEL_TRACE) !== self::LEVEL_TRACE) {
            return;
        }
        //if we are in level trace we want to have the trace in the log
        // the exception trace is more readable
        $e = new Exception();
        $trace = explode("\n", $e->getTraceAsString());
        $event->trace = [];
        //we cut off the stack frames where we are in the logger and renumber the output
        array_splice($trace, 0, $i);
        foreach($trace as $key => $value) {
            $event->trace[] = preg_replace('/^#[0-9]+ /', '#'.$key.' ', $value);
        }
        $event->trace = join("\n", $event->trace);
    }
    
    /**
     * If we have a trace, we also can set the worker we are in
     * @param ZfExtended_Logger_Event $event
     * @param array $trace
     */
    protected function fillWorker(ZfExtended_Logger_Event $event, array $trace) {
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
        if(!empty($_SERVER['HTTP_HOST'])) {
            $event->httpHost = $_SERVER['HTTP_HOST'];
        }
        if(!empty($_SERVER['REQUEST_URI'])) {
            $event->url = $_SERVER['REQUEST_URI'];
        }
        if(!empty($_SERVER['REQUEST_METHOD'])) {
            $event->method = $_SERVER['REQUEST_METHOD'];
        }
        
        if(defined('APPLICATION_VERSION')) {
            $event->appVersion = APPLICATION_VERSION;
        }
        else {
            $event->appVersion = 'not defined yet';
        }
        
        if(Zend_Session::isStarted() && !Zend_Session::isDestroyed()) {
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
    public function formatMessage($message, array $extra = null){
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
        if($writer->isEnabled()) {
            $this->writer[$name] = $writer;
        }
    }
    
    /**
     * returns the levelname to the given LEVEL_CONST integer
     * @param int $level
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
     * returns the internally configured domain
     * @return string
     */
    public function getDomain() {
        return $this->domain;
    }
    
    /**
     * @param string $method
     * @param array $arguments
     * @throws InvalidArgumentException
     */
    public function __call($method, $arguments) {
        $level = $this->levelFromString($method);
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
    
    /**
     * returns the numeric level to the given string, example 'fatal' returns 1
     * @param $level string
     * @return integer
     */
    protected function levelFromString($level) {
        $level = 'LEVEL_'.strtoupper($level);
        if (($level = array_search($level, $this->logLevels)) === false) {
            return self::LEVEL_INFO; //default level on invalid level given
        }
        return $level;
    }
}