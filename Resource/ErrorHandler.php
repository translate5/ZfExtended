<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
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
 * Eigener Errorhandler
 *
 * - Wirft für alle abfangbaren Fehler eine Zend_Exception
 * - Ermöglicht TypeHinting für alle Typen in PHP
 * - E_USER_NOTICE wird nicht als Fehler gewertet, aber an allen relevanten Stellen 
 *   mit geloggt. D. h., einziger Loggingunterschied ist, dass der Fehler- und 
 *   http-Responsecode nicht auf 500 sondern auf 200 steht
 *
 *
 */
class ZfExtended_Resource_ErrorHandler extends Zend_Application_Resource_ResourceAbstract {
    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('ZfExtended_Resource_InitRegistry');
        $config = Zend_Registry::get('config');
        register_shutdown_function(array($this, 'handleFatalError'), $config);
        Zend_Registry::set('errorCollect', false);
        if(isset($config->runtimeOptions->errorCollect)){
            Zend_Registry::set('errorCollect', (boolean) $config->runtimeOptions->errorCollect);
            Zend_Registry::set('errorCollector', array());
        }
        set_error_handler(array('ZfExtended_Resource_ErrorHandler', 'errorHandler'));
    }
    
    /**
     * @param Zend_Config $config
     */
    public function handleFatalError(Zend_Config $config) {
        $error = error_get_last();
        if(empty($error)) {
            return;
        }
        if(!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }
        if(!empty($config->runtimeOptions->showErrorsInBrowser)) {
            $out = ob_get_clean();
        }
        else {
            $out = '';
            ob_get_length() && ob_clean(); //show only a white page
        }
        echo '<h1>Internal Server Error</h1>'."\n".$out;
        $log = new ZfExtended_Log(false);
        $log->logFatal($error);
    }
    
    /**
     * - Führt typehinting für alle Types ein auch für die Types, für die php es von Haus aus nicht unterstützt
     * - Kümmert sich ums errorCollecting, wenn aktiviert in Zend_Registry "errorCollect"
     * - E_USER_NOTICE wird nicht als Fehler gewertet, aber an allen relevanten Stellen 
     *   mit geloggt. D. h., einziger Loggingunterschied ist, dass der Fehler- und 
     *   http-Responsecode nicht auf 500 sondern auf 200 steht
     *
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline ) {
        if($errno == E_RECOVERABLE_ERROR) {
            if(preg_match('/^Argument (\d)+ passed to (?:(\w+)::)?(\w+)\(\) must be an instance of (\w+), (\w+) given/', $errstr, $match)) {
                if($match[4] == $match[5]) return true;
            }
        }
        $errorCollect = Zend_Registry::get('errorCollect');
        if($errorCollect){
            $errors = Zend_Registry::get('errorCollector');
            $error = new stdClass();
            $error->errno = $errno;
            $error->_errorMessage = 'ErrorCollect: '.$errstr;
            $error->_errorTrace = self::getTrace();
            $error->_errorCode = 500;
            if($errno == E_USER_NOTICE) {
                $error->_errorCode = 200; 
            }
            $error->errfile = $errfile;
            $error->errline = $errline;
            $errors[] = $error;
            Zend_Registry::set('errorCollector', $errors);
            return true;
        }
        throw new Zend_Exception($errstr."; File: ".$errfile."; Line: ".$errline."; errno: ".$errno, 0 );
    }
    
    /**
     * @return Gibt debug_backtrace als var_dump in einem String zurück 
     */
    public static function getTrace(){
        try {
            throw new Zend_Exception();
        } catch (Zend_Exception $e) {
            return $e->getTraceAsString();
        }
    }
}