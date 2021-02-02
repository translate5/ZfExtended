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
 * Schreibt das Errorlog und versendet Mail an Admin bei Fehler
 *
 *   Browser angezeigt, sonst nur eine allgemein Fehlermeldung mit Kontaktdaten
 * - E_USER_NOTICE wird nicht als Fehler gewertet, aber an allen relevanten Stellen
 *   mit geloggt. D. h., einziger Loggingunterschied ist, dass der Fehler- und
 *   http-Responsecode nicht auf 500 sondern auf 200 steht
 */
class ErrorController extends ZfExtended_Controllers_Action
{
    /**
     * @var ZfExtended_Zendoverwrites_Translate
     */
    protected $_translate;
    /**
     * contains the caught exception
     * @var Exception
     */
    protected $exception = NULL;
    
    /**
     *
     * @var string
     */
    protected $route;
    
    /**
     * @var ZfExtended_Logger
     */
    protected $logger;

    /**
     * Initialisiert Variablen
     */
    public function init()
    {
        $this->route = get_class(Zend_Controller_Front::getInstance()->getRouter()->getCurrentRoute());
        $this->_translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        $this->logger = Zend_Registry::get('logger');
        $this->exceptionInit();
    }

    /**
     * Initialisiert im exceptionbasierten Errorprozess die Exceptions
     */
    protected function exceptionInit()
    {
        //the error caught by the Zend_Controller_Plugin_ErrorHandler
        $caughtError = $this->_getParam('error_handler');
        //$caughtError->request → the original request
        //$caughtError->exception → the exception
        //$caughtError->type as defined in Zend_Controller_Plugin_ErrorHandler
        $this->exception = $caughtError->exception;
        $this->view->errorCode = null; // instead of E9999 we just send nothing to the GUI if there is no errorCode
        
        switch($caughtError->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
                $httpCode = 404;
                break;
            default:
                $e = $this->exception;
                if($e instanceof ZfExtended_ErrorCodeException){
                    $httpCode = $e->getHttpReturnCode();
                    $this->view->errorCode = $e->getErrorCode();
                }
                elseif(empty($e)) {
                    $httpCode = 500;
                }
                else {
                    $httpCode = $e->getCode() ?? 500;
                }
                break;
        }
        
        $httpMessage = $this->responseCodeAsText($httpCode);
        if(empty($httpMessage)) {
            $httpCode = 500;
            // get message again if changed
            $httpMessage = $this->responseCodeAsText($httpCode);
        }
        
        $this->getResponse()->setHttpResponseCode($httpCode);
        //ExtJS does not parse the HTTP Status well on file uploads.
        // In this case we deliver the status as additional information
        //if($this->isRestRoute() && !empty($_FILES)) {
            $this->view->httpStatus = $httpCode;
        //}
        if($this->exception instanceof ZfExtended_Exception){
            $this->view->errorMessage = $this->logger->formatMessage($this->exception->getMessage(), $this->exception->getErrors());
        }
        else {
            $this->view->errorMessage = $this->exception->getMessage();
        }
        $this->view->message = $httpMessage;
        $this->view->success = false;
    }
    
    /**
     * returns the given HTTP code as text
     * @param string $code
     * @return string
     */
    protected function responseCodeAsText($code) {
        $codeMap = Zend_Http_Response::responseCodeAsText();
        $codeMap['422'] = 'Unprocessable Entity';
        if(empty($codeMap[$code])) {
            return null;
        }
        return $codeMap[$code];
    }

    /**
     * Wird im Error-Falle ausgeführt
     */
    public function errorAction()
    {
        $this->view->translate = $this->_translate;
        
        $missingController = $this->exception instanceof Zend_Controller_Dispatcher_Exception && strpos($this->exception->getMessage(), 'Invalid controller specified') !== false;
        $missingAction = $this->exception instanceof Zend_Controller_Action_Exception && $this->exception->getCode() == '404';
        $notFound = $this->exception instanceof ZfExtended_NotFoundException;
        $loggingEnabled = true;
        
        // add errors
        if($this->exception instanceof ZfExtended_Exception){
            $errors = $this->exception->getErrors();
            $loggingEnabled = $this->exception->isLoggingEnabled();
            if(!empty($errors) && !empty($errors['errors'])) {
                $this->view->errors = $errors['errors'];
            }
            if(!empty($errors) && !empty($errors['errorsTranslated'])) {
                $this->view->errorsTranslated = $errors['errorsTranslated'];
            }
        }

        $isHttp404 = false;
        if(($missingAction || $notFound || $missingController) && !$this->isRestRoute()) {
            $isHttp404 = true;
            $this->view->errorMessage = $this->_translate->_('Seite nicht gefunden: ').$_SERVER['REQUEST_URI'].$this->_translate->_('/ Aufruf erfolgte durch IP: ').$_SERVER['REMOTE_ADDR'];
        }
        
        if($loggingEnabled){
            if($isHttp404 || ($this->exception instanceof ZfExtended_Models_Entity_NotFoundException && $this->isRestRoute())){
                $this->logger->exception($this->exception, [
                    'level' => ZfExtended_Logger::LEVEL_INFO,
                    'eventCode' => 'E1019',
                ]);
            }
            else{
                $this->logger->exception($this->exception);
            }
        }
        $this->renderScript($this->initAndGetRenderScript($isHttp404));
    }
    
    /**
     * returns true if request is a REST Request
     */
    protected function isRestRoute(){
        $restRoute = 'Zend_Rest_Route';
        if($this->route === $restRoute || $this->route === 'ZfExtended_Controller_RestLikeRoute' || $this->route === 'ZfExtended_Controller_RestFakeRoute'){
            return true;
        }
        return is_subclass_of($this->route, $restRoute);
    }
    
    /**
     * returns the error script to render
     * @param bool $is404
     * @return string
     */
    protected function initAndGetRenderScript($is404) {
        $error = error_get_last();
        if(!empty($error)) {
            //if there were warnings or notices before, we have to clear them to get valid browser output
            ob_get_length() && ob_clean();
        }
        
        if($this->exception && $this->exception->getCode() == '503'){
            Zend_Layout::getMvcInstance()->disableLayout();
            if($this->_getParam('controller') === 'cron') {
                return 'error/maintenance_cron.phtml';
            }
            return 'error/maintenance.phtml';
        }
        if($this->isRestRoute()){
            Zend_Layout::getMvcInstance()->disableLayout();
            return 'error/errorRest.phtml';
        }
        $config = Zend_Registry::get('config');
        if(!empty($config->runtimeOptions->showErrorsInBrowser)){
            $this->view->exception = $this->exception;
            return 'error/errorAdmin.phtml';
        }
        if($is404 || $this->exception instanceof ZfExtended_NotFoundException){
            return 'error/error404.phtml';
        }
        return 'error/error.phtml';
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
