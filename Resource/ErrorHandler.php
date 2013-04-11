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
        Zend_Registry::set('showErrorsInBrowser', false);
        if(isset($config->runtimeOptions->showErrorsInBrowser)){
            Zend_Registry::set('showErrorsInBrowser', (boolean) $config->runtimeOptions->showErrorsInBrowser);
        }
        Zend_Registry::set('errorCollect', false);
        if(isset($config->runtimeOptions->errorCollect)){
            Zend_Registry::set('errorCollect', (boolean) $config->runtimeOptions->errorCollect);
            Zend_Registry::set('errorCollector', array());
        }
        set_error_handler(array('ZfExtended_Resource_ErrorHandler', 'errorHandler'));
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
    /*
     * 
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