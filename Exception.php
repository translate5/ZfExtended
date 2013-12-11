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
     * @var Zend_Translate 
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
     * Construct the exception
     *
     * @param  string $msg (Message gets translated by ZfExtended_Exception)
     * @param  int $code
     * @param  Exception $previous
     * @param  boolean $translate decides, if the msg should be sent to translation-process.
     *          do not sent error-messages to translation process which are only meant 
     *          for logging and which contain variable information
     * @return void
     */
    public function __construct($msg = '', $code = 0, Exception $previous = null,$translate=false)
    {
        $this->setMessage($msg, $translate);
        parent::__construct($this->message, (int) $code, $previous);
    }
    
    /**
     * sets the internal exception message
     * @param string $msg
     * @param boolean $translate optional, set to true if the message should be translated 
     */
    public function setMessage($msg, $translate = false) {
        if($translate){
            $this->_translate = Zend_Registry::get('Zend_Translate');
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
     * @return boolean
     */
    public function isLoggingEnabled() {
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