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

class ZfExtended_Exception extends Zend_Exception {
    /**
     * the error/event level of this exception (how "important" that error is)
     * By default all Exceptions are of level error
     * @var integer
     */
    protected $level = ZfExtended_Logger::LEVEL_ERROR;
    
    /**
     * @var ZfExtended_Zendoverwrites_Translate 
     */
    protected $_translate;
    
    /**
     * internal errors store
     * @var array
     */
    protected $errors;
    
    /**
     * internal origin store
     * @var string
     */
    protected $origin = 'core';
    
    /**
     * FIXME should be replaced with a loglevel based way
     * Flag if logging for this exception is enabled / disabled
     * @var boolean
     */
    protected $loggingEnabled = true;
    
    /**
     * @var string
     */
    protected $defaultMessage = '';
    
    /**
     * @var boolean
     */
    protected $defaultMessageTranslate = false;
    
    /**
     * @var integer
     */
    protected $defaultCode = 0;
    
    
    /**
     * Construct the exception
     *
     * @param  string $msg (Message gets translated by ZfExtended_Exception)
     * @param  int $code
     * @param  Exception $previous
     * @param  string $origin optional, defaults to core. Can be the plugin name, or another system identifier
     * @return void
     */
    public function __construct($msg = '', $code = 0, Exception $previous = null, $origin = 'core')
    {
        if((int)$code === 0){
            $code = $this->defaultCode;
        }
        if($msg == ''){
            $this->setMessage($this->defaultMessage, $this->defaultMessageTranslate);
        }
        else {
            $this->setMessage($msg);
        }
        $this->setOrigin($origin);
        parent::__construct($this->message, (int) $code, $previous);
    }
    
    /**
     * sets the internal exception message
     * @param string $msg
     * @param boolean $translate optional, set to true if the message should be translated 
     */
    public function setMessage($msg, $translate = false) {
        if($translate){
            $this->_translate = ZfExtended_Zendoverwrites_Translate::getInstance();
            $msg = $this->_translate->_($msg);
        }
        $this->message = $msg;
        
        //FIXME add a flag here, to find out if it is the default message or a custom message. 
        // bring this info (custom message or default message) to the frontend, so that we can react there better
        
        // Also we should differ in the excption messages for the different log levels.
        // A Exception should contain content for each loglevel. The message ported to the frontend for example should not contain debug data.
    }
    
    /**
     * stores the given errors internally
     * @param array $errors
     * @deprecated refactor the called exception instance to errorCodeException and use the extra/data container there  
     * //FIXME searc for usages and refactor it 
     */
    public function setErrors(array $errors) {
        $this->errors = $errors;
    }

    /**
     * return the internally stored errors
     * @deprecated refactor the called exception instance to errorCodeException and use the extra/data container there  
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * stores the origin of the exception (plugin name, etc), defaults to core
     * @param string $origin
     */
    public function setOrigin(string $origin) {
        $this->origin = $origin;
    }

    /**
     * return the internally stored origin
     * @return string
     */
    public function getOrigin() {
        return $this->origin;
    }
    
    /**
     * return the error/event level of this exception (how "important" that error is)
     * @return integer
     */
    public function getLevel() {
        return $this->level;
    }
    
    /**
     * Adds the additional not translated error information to the log output
     * {@inheritDoc}
     * @see Zend_Exception::__toString()
     */
    public function __toString() {
        $errors = '';
        if(!empty($this->errors)) {
            $errors = "\n\n Additional error data: ".print_r($this->errors,1);
        }
        return parent::__toString().$errors;
    }
    
    /**
     * FIXME should be replaced with a loglevel based way
     * 
     * returns true if logging should be done for this exception
     * We can force to enable the logging even if the exception was coded not to log by setting this in the config:
     * runtimeOptions.logging.default.delete.index.ZfExtended_BadMethodCallException = true 
     * where default is the module, delete the controller and index the action to be considered
     * Module, Controller and Action are each optional, so the config syntax would be:
     * runtimeOptions.logging.[default.[delete.[index.]]]EXCEPTION_CLASS_NAME
     * the module part can be overwritten by BaseIndex::setModule, so caution in configuration here. 
     * @return boolean
     */
    public function isLoggingEnabled() {
        $config = Zend_Registry::get('config');
        /**
         * Startpoint in the Config tree
         */
        $logConf = $config->runtimeOptions->logging;
        
        /**
         * the names of needed parts (module, action, etc)
         */
        $exception = get_class($this);
        $mod = Zend_Registry::get('module'); //warning this can be changed be BaseIndex::setModule
        $contr = Zend_Registry::get('controller');
        $action = Zend_Registry::get('action');
        
        /**
         * all possible config paths are defined in this array
         * @var unknown_type
         */
        $pathsToCheck = array(
            array($exception),
            array($mod, $exception),
            array($mod, $contr, $exception),
            array($mod, $contr, $action, $exception),
        );
        
        /**
         * @return boolean if a config was found, NULL if nothing was configured for the path
         */
        $checkPath = function($start, $path) use (&$checkPath) {
            if(!is_null($start) && !($start instanceof Zend_Config)) {
                throw new Exception('start is not NULL and not instanceof Zend_Config');
            }
            $part = array_shift($path);
            if(is_null($start) || !isset($start->$part)) {
                return null;
            }
            if($start->$part instanceof Zend_Config) {
                return $checkPath($start->$part, $path);
            }
            return (bool) $start->$part;
        };

        //walk over each $pathToCheck and look ap the config for it
        foreach($pathsToCheck as $path) {
            $res = $checkPath($logConf, $path);
            if(!is_null($res)) {
                return $res;
            }
        }
        return $this->loggingEnabled;
    }
    
    /**
     * enables / disables the logging for this exception
     * @param boolean $enabled
     */
    public function setLogging($enabled = true) {
        $this->loggingEnabled = $enabled;
    }
}