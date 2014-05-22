<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

  END LICENSE AND COPYRIGHT 
 */

class ZfExtended_Exception extends Zend_Exception {
    /**
     *
     * @var ZfExtended_Zendoverwrites_Translate 
     */
    protected $_translate;
    
    /**
     * internal errors store
     * @var array
     */
    protected $errors;
    
    /**
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
     * @return void
     */
    public function __construct($msg = '', $code = 0, Exception $previous = null)
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
    }
    
    /**
     * stores the given errors internally
     * @param array $errors
     */
    public function setErrors(array $errors) {
        $this->errors = $errors;
    }

    /**
     * return the internally stored errors
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
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
        $checkPath = function(Zend_Config $start, $path) use (&$checkPath) {
            $part = array_shift($path);
            if(!isset($start->$part)) {
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