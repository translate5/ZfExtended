<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

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
 */
class  ZfExtended_Log extends ZfExtended_Mail{
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
     *  @param boolean initView entscheidet, ob view initialisiert wird
     *      (Achtung: Bei false ist die Verwendung von Mailtemplates mit ZfExtended_Mail nicht möglich)
     *      Default: true
     */
    public function __construct($initView = true) {
        parent::__construct($initView);
        $this->_config = Zend_Registry::get('config');
        $this->_className = get_class($this);
        $this->_className .= ' on '.$_SERVER['HTTP_HOST'];
        try {
            $session = new Zend_Session_Namespace();
            $this->_isFork = $session->isFork;
        }
        catch (Exception $e) {
        }
        if($this->_isFork){
            $this->_className .= ' (FORK!)';
        }
    }
    /**
     * Loggt eine Exception
     *
     * @param string message Kurzzusammenfassung des Fehlers - wird auch im Mailsubject verwendet
     * @param string longMessage Alles, was Du dazu zu sagen hast | NULL (default)
     */
    public function logError(string $message,string $longMessage=NULL){
        $viewRenderer = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
        $longMessage .= $this->getUrlLogMessage();
        $message = $this->addUserInfo($message);
        error_log($this->_className.': '.$message.
                "\r\n                       ".$longMessage);
        $this->sendMailDefault($message);
        $this->sendMailMinidump($message,$longMessage);
    }
    /**
     * Loggt eine Exception
     * 
     * @param Exception
     */
    public function logException(Exception $exception){
        $message = $this->addUserInfo($exception->getMessage());
        $trace = $this->elog($exception);
        $this->sendMailDefault($message);
        $this->sendMailMinidump($message, $trace);
        $prev = $exception->getPrevious();
        if(! empty($prev)) { //FIXME this only if debugging enabled → da gabs doch schon ein flag???
            $this->elog($prev);
        }
    }
    
    /**
     * adds informations abaout the current user / session to the given string (error message)
     * @param string $msg
     * @return string
     */
    protected function addUserInfo($msg) {
        $sessionUser = new Zend_Session_Namespace('user');
        if(!empty($sessionUser->data->login)) {
            $msg .= "\n".' current user: '.$sessionUser->data->login;
        }
        return $msg;
    }
    
    /**
     * error_logs the given exception
     * @param Exception $e
     * @return string returns the trace as string
     */
    protected function elog(Exception $e) {
        $message = $e->getMessage();
        $trace = $e->getTraceAsString();
        $trace .= $this->getUrlLogMessage();
        error_log($this->_className.': '.$message.
                "\r\n                       Trace: \r\n".$trace);
        
        return $trace;
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
     * Holt auf Basis des views mit dem viewhelper getUrl die URL, wenn
     * der view schon vorhanden ist. Dann inkl. ggf. vorhandener POST-Parameter. Ansonsten $_SERVER['REQUEST_URI']
     * 
     * - Ergänzt URL um davor stehenden Infotext für das Log
     * 
     * - Achtung: Die Namen von Passwort-Post-Felder müssen hier aufgenommen sein,
     *   sonst werden passwörter ggf. als Klartext per Mail versandt im Fehlerfall
     * 
     * @return string 
     */
    public function getUrlLogMessage(){
        $viewRenderer = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
        if(isset($viewRenderer->view)){
            return "Aufgerufene URL inkl. ggf. vorhandener POST-Parameter als get-Parameter: \r\n".
                    $viewRenderer->view->getUrl(array('password','passwd','passwdCheck'));
        }
        return "Aufgerufene URL - Rückgabe von _SERVER['REQUEST_URI']: \r\n".
                $_SERVER['REQUEST_URI'];
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
        $this->setMail();
        $this->setContent(substr($subject, 0, 120), $subject."\r\n\r\n".(string)$message);
        $this->send($this->_config->resources->mail->defaultFrom->email,
                $this->_config->resources->mail->defaultFrom->name);
    }
}