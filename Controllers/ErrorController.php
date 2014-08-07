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

/**
 * Schreibt das Errorlog und versendet Mail an Admin bei Fehler, falls runtimeOptions.errorCollect = 1 via application.ini oder Zend_Registry gesetzt wurde
 *
 * - wenn $this->_session->runtimeOptions->showErrorsInBrowser auf 1, wird die Fehlermeldung mit trace im
 *   Browser angezeigt, sonst nur eine allgemein Fehlermeldung mit Kontaktdaten
 * - E_USER_NOTICE wird nicht als Fehler gewertet, aber an allen relevanten Stellen
 *   mit geloggt. D. h., einziger Loggingunterschied ist, dass der Fehler- und
 *   http-Responsecode nicht auf 500 sondern auf 200 steht
 */
class ErrorController extends ZfExtended_Controllers_Action
{
    protected $_session;
    /**
     * @var object errors from errorhandler
     */
    protected $_errorhandlerErrors;
    /**
     * @var array Zusammengestellt für die Log- und Viewausgabe
     */
    protected $_errors;
    /**
     * @var ZfExtended_Log
     */
    protected $_log;
    /**
     * @var ZfExtended_Zendoverwrites_Translate
     */
    protected $_translate;
    /**
     * @var boolean Definiert, ob der ErrorController durch einen errorCollect (true) oder durch eine ErrorException aufgerufen wurde (false)
     */
    protected $_errorCollect = false;
    /**
     * @var array
     */
    protected $_getParams = NULL;
    /**
     * @var Zend_Exception | mixed
     */
    protected $_exception = NULL;
    /**
     * @var string error-script to render
     */
    protected $_renderScript = NULL;
    /**
     * @var integer
     */
    protected $_showErrorsInBrowser = 0;
    /**
     * @var boolean this is made to distinguish between 
     * ZfExtended_Models_Entity_NotFoundException and ZfExtended_NotFoundException
     * this is a hack, but something else would bean to refactor the whole errorhandling process
     * @todo refactor errorhandling to be able to catch the ZfExtended_NotFoundException and sent 404 status code
     */
    protected $_isHttp404 = false;
    /**
     *
     * @var string  
     */
    protected $route;
    /**
     * @var array Liste aller gültigen httpResponseCodes (status code 409 wird nicht ins error-log geschrieben)
     *
     */
    protected $_httpResponseCodes = array(
        '100' => 'Continue',
        '101' => 'Switching Protocols',
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Moved Temporarily',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',//no errorlogging on 401, because it is a normal exception on session timeout
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '407' => 'Proxy Authentication Required',
        '408' => 'Request Time-out',
        '409' => 'Conflict', 
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Request Entity Too Large',
        '414' => 'Request-URI Too Large',
        '415' => 'Unsupported Media Type',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
        '503' => 'Service Unavailable',
        '504' => 'Gateway Time-out',
        '505' => 'HTTP Version not supported'
    );

    /**
     * Initialisiert Variablen
     */
    public function init()
    {
        $this->route = get_class(Zend_Controller_Front::getInstance()->getRouter()->getCurrentRoute());
        try {
            $config = Zend_Registry::get('config');
            if(isset($config->runtimeOptions->showErrorsInBrowser)){
                $this->_showErrorsInBrowser = Zend_Registry::get('showErrorsInBrowser');
            }
            if(isset($config->runtimeOptions->errorCollect)){
                $this->_errorCollect = Zend_Registry::get('errorCollect');
            }
        }
        catch (Exception $e) {
        }
        $this->_translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        $this->_log = ZfExtended_Factory::get('ZfExtended_Log');
        if($this->_errorCollect){
            $this->errorcollectInit();
        }
        else{
            $this->exceptionInit();
        }
        if(count($this->_errors)==0){
            throw new Zend_Exception('ErrorController ausgeführt, aber keine Fehler übergeben.');
        }
        $this->verifyErrorCodes();
        $this->viewInit();
    }
    /**
     * Prüft errorCodes gegen $this->_httpResponseCodes
     */
    protected function verifyErrorCodes()
    {
        foreach($this->_errors as $key => &$error){
            if(!array_key_exists($this->_errors[$key]->_errorCode, $this->_httpResponseCodes)){
                $this->_errors[$key]->_errorCode = 500;
            }
        }
    }
    /**
     * Initialisiert im exceptionbasierten Errorprozess die Exceptions
     */
    protected function errorcollectInit()
    {
        $this->_errors = Zend_Registry::get('errorCollector');
        $this->_getParams = $this->_log->getUrlLogMessage();
		if(count($this->_errors)==0){
			//es wurde eine Exception geworfen, deaktiviere errorCollect und initialisiere die Exception
			Zend_Registry::set('errorCollect',false);
			$this->_errorCollect = false;
			$this->exceptionInit();
		}
    }

    /**
     * Initialisiert im exceptionbasierten Errorprozess die Exceptions
     */
    protected function exceptionInit()
    {
        $this->_errorhandlerErrors = $this->_getParam('error_handler');
        try {
            $this->_getParams = $this->_errorhandlerErrors->request->getParams();
        }
        catch (Exception $e) {
            $this->_getParams = $this->_log->getUrlLogMessage();
        }
        $this->_errors[0] = new stdClass();
        try {
            $this->_exception = $this->_errorhandlerErrors->exception;
            $this->_errors[0]->_errorMessage = $this->_exception->getMessage();
            $this->_errors[0]->_errorCode = (int)$this->_exception->getCode();
            $this->_errors[0]->_errorTrace = $this->_exception->getTraceAsString();
        }
        catch (Exception $e) {
            $this->_errors[0]->_errorMessage = 'ZfExtended: Unknown Error';
            $this->_errors[0]->_errorCode = 500;
            $this->_errors[0]->_errorTrace = debug_backtrace();
        }

        if($this->_errors[0]->_errorCode != 0){
            return;
        }
        switch($this->_errorhandlerErrors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
                $this->_errors[0]->_errorCode = 404;
                break;
            default:
                $this->_errors[0]->_errorCode = 500;
                break;
        }
    }
    /**
     * @return object den Fehler mit dem höchsten _errorCode der Fehler aus $this->_errors
     */
    protected function getErrorWithHighesErrorCode()
    {
        $code = 0;
        foreach($this->_errors as $error){
            if($error->_errorCode > $code){
                $code = $error->_errorCode;
                $r = $error;
            }
        }
        return $r;
    }
    /**
     * Stellt aus ->_errno, _errorMessage, _errorTrace, errfile, errline aller
     * in $this->_errors abgelegten Fehler
     * einen sinnvoll formatierten String zusammen
     *
     * - funktioniert nur, wenn all diese Unterobjekte auch im Fehler existieren
     */
    protected function buildErrorCollectLogLongMessage()
    {
        $m = '';
        foreach($this->_errors as $error){
            $m .= "                       ".$error->_errorMessage."\r\n";
            if($error->_errorCode > 202){
                $m .="                       File: ".$error->errfile."; Line: ".$error->errline."; errno: ".$error->errno."\r\n".
                    "                       Trace: ".$error->_errorTrace."\r\n\r\n";
            }
        }
        return ltrim($m);
    }
    /**
     * Initialisiert den view abhängig von Fehlerart und Art der Route
     */
    protected function viewInit()
    {
        $this->view->errors = $this->_errors;
        $this->view->getParams   = $this->_getParams;
        $this->view->errorCollect   = $this->_errorCollect;
        $this->view->translate = $this->_translate;
        if($this->isRestRoute()){
            Zend_Layout::getMvcInstance()->disableLayout();
            $this->_renderScript = 'error/errorRest.phtml';
        }
        else{
            $this->_renderScript = 'error/error.phtml';
            if($this->_showErrorsInBrowser == 1){
                $this->_renderScript = 'error/errorAdmin.phtml';
            }
            if($this->_errors[0]->_errorCode === 404){
                $this->_renderScript = 'error/error404.phtml';
                //FIXME wie machen dass das immer in entwicklungsumgebung??? 
                $this->_renderScript = 'error/errorAdmin.phtml';
            }
        }
        
        $missingController = $this->_exception instanceof Zend_Controller_Dispatcher_Exception && strpos($this->_exception->getMessage(), 'Invalid controller specified') !== false;
        $missingAction = $this->_exception instanceof Zend_Controller_Action_Exception && $this->_exception->getCode() == '404';
        $notFound = $this->_exception instanceof ZfExtended_NotFoundException;
        if(($missingAction || $notFound || $missingController) && !$this->isRestRoute()) {
            $this->_isHttp404 = true;
            $this->view->errors[0]->_errorMessage = $this->_translate->_('Seite nicht gefunden: ').$_SERVER['REQUEST_URI'].$this->_translate->_('/ Aufruf erfolgte durch IP: ').$_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * returns true if request is a REST Request
     */
    protected function isRestRoute(){
        $restRoute = 'Zend_Rest_Route';
        if($this->route === $restRoute){
            return true;
        }
        return is_subclass_of($this->route, $restRoute);
    }
    
    /**
     * Wird im Error-Falle ausgeführt
     */
    public function errorAction()
    {
        $highestError = $this->getErrorWithHighesErrorCode();
        $loggingDisabled = (($this->_exception instanceof ZfExtended_Exception) && ! $this->_exception->isLoggingEnabled());
        
        if($loggingDisabled){
            //do nothing here
        }
        elseif($this->_isHttp404){
            $this->_log->log404($highestError->_errorMessage);
        }
        elseif($this->_errorCollect){
            $this->_log->logError($highestError->_errorMessage,  $this->buildErrorCollectLogLongMessage());
        }
        else{
            $this->_log->logException($this->_exception);
        }
        $this->getResponse()->setHttpResponseCode($highestError->_errorCode);
        $this->renderScript($this->_renderScript);
    }

    /**
     * Wird von JS im Falle eines JS-Fehlers getriggert und speist diesen Fehler
     * in die Fehlerbehandlung ein
     *
     * @throws Zend_Exception
     * @return void
     */
    public function jserrorAction(){
        throw new Zend_Exception(
                'Fehler im Javascript. Die folgende Meldung wurde vom JS übergeben: '.
                $this->_request->getParam('jsError').
                '  Content of $_SERVER had been: '.  print_r($_SERVER,true));
    }

}
/**
 * Pseudo-Klasse zur Verwendung, falls Zend_Translate noch nicht initialisiert wurde.
 */
class pseudoTranslate
{
    /**
     * Gibt als Pseudo-Translate den übergebenen Parameter wieder zurück
     *
     * @param string $param
     * @return string $param
     */
    public function _(string $param) {
        return $param;
    }

}
