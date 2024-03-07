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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Klasse zur Kapselung des Loggings und Mailversands von Logmeldungen
 * @deprecated Use ZfExtended_Logger where possible
 */
class  ZfExtended_Log extends ZfExtended_TemplateBasedMail {
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
    
    /**
      * @var Zend_Config_Ini
      */
    protected $_config;
    /**
      * @var string
      */
    protected $_className = '';
    /**
      * @var boolean
      */
    protected $_isFork = false;

    /**
     * initiiert das interne Mail und View Object
     *
     *  @param bool initView entscheidet, ob view initialisiert wird
     *      (Achtung: Bei false ist die Verwendung von Mailtemplates mit ZfExtended_TemplateBasedMail nicht möglich)
     *      Default: true
     */
    public function __construct($initView = true) {
        parent::__construct($initView);
        $this->_config = Zend_Registry::get('config');
        $this->_className = get_class($this);
        if(!empty($_SERVER['HTTP_HOST'])) {
            $this->_className .= ' on '.$_SERVER['HTTP_HOST'];
        }
    }

    /**
     * @param string message
     * @param string|null $longMessage
     * @deprecated
     */
    public function logError(string $message, string $longMessage = null): void
    {
        $message = $this->addUserInfo($message);
        error_log($this->_className . ': ' . $message .
            "\r\n                       " . $longMessage);
        $this->sendMailDefault($message);
        $this->sendMailMinidump($message, $longMessage);
    }
    
    /**
     * adds informations abaout the current user / session to the given string (error message)
     * @param string $msg
     * @return string
     */
    protected function addUserInfo($msg) {
        $auth = ZfExtended_Authentication::getInstance();
        if(!empty($auth->isAuthenticated())) {
            $msg .= "\n".' current user: '.$auth->getLogin();
        }
        return $msg;
    }
    
    /**
     * logs a message
     * @param string $subject
     * @param string $message
     */
    public function log($subject, $message) {
        error_log($subject."\n".$message);
        $this->sendMail($subject, $message);
    }
    
    /**
     * loggs a fatal error
     * @param array $error
     */
    public function logFatal(array $error) {
        $ro = $this->_config->runtimeOptions;
        if($ro && $ro->disableErrorMails && $ro->disableErrorMails->default == 1){
            return; // no extra logging here since fatals are always logged
        }
        $msg  = 'Given Fatal Error Info: '.print_r($error,1)."\n\n";
        $msg .= 'Server Data: '.print_r($_SERVER,1);
        $this->sendMail($this->_className.' - FATAL ERROR', $msg);
    }

    /**
     * Loggt 404-Fehler
     *
     * @param string $message
     */
    public function log404(string $message){
        $ro = $this->_config->runtimeOptions;
        if($ro && $ro->disableErrorMails && $ro->disableErrorMails->notFound == 1){
            error_log($this->_className.': Versand der NotFound-Fehlermails deaktiviert: '.$message);
            return;
        }
        $this->sendMail($message);
    }

    protected function sendMailDefault(string $message){
        $ro = $this->_config->runtimeOptions;
        if($ro && $ro->disableErrorMails && $ro->disableErrorMails->default == 1){
            error_log($this->_className.': Versand der Default-Fehlermails ohne dump deaktiviert - Subject: '.
                    $message);
            return;
        }
        $this->sendMail($this->_className.' - Kurzmeldung: '.$message);
    }
    protected function sendMailMinidump(string $message, string $data){
        $ro = $this->_config->runtimeOptions;
        if($ro && $ro->disableErrorMails && $ro->disableErrorMails->minidump == 1){
            error_log($this->_className.': Versand der Minidump-Fehlermails deaktiviert - Subject: '.
                    $message.' Attachment Size: '.strlen($data));
            return;
        }
        $this->sendMail($this->_className.': '.$message, $data);
    }

    protected function sendMail (string $subject, $message=NULL) {
        //for TRANSLATE-600 only:
        $this->setMail();
        $this->setContent(substr($subject, 0, 120).$this->getAffectedTaskGuid(), $subject."\r\n\r\n".(string)$message);
        $receiver = $this->_config->resources->ZfExtended_Resource_Logger->writer->mail->receiver ?? $this->_config->resources->mail->defaultFrom->email;
        if($receiver instanceof Zend_Config){
            $receiver = $receiver->toArray();
        }
        if(is_array($receiver)){
            foreach($receiver as $one) {
                $this->send($one, $this->_config->resources->mail->defaultFrom->name);
            }
        }
        else {
            $this->send($receiver, $this->_config->resources->mail->defaultFrom->name);
        }
    }
    
    /**
     * For a better debugging with a fast implementation we introduced TRANSLATE-600
     * @return string
     */
    protected function getAffectedTaskGuid() {
        $prefix = ' taskGuid: ';
        if(isset($_SESSION) && isset($_SESSION['Default']) && isset($_SESSION['Default']['taskGuid'])) {
            return $prefix.$_SESSION['Default']['taskGuid'];
        }
        if(Zend_Registry::isRegistered('affected_taskGuid')) {
            return $prefix.Zend_Registry::get('affected_taskGuid');
        }
        return '';
    }
}