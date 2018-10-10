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
     * TODO remove me with PHP7!
     * - Führt typehinting für alle Types ein auch für die Types, für die php es von Haus aus nicht unterstützt
     * - E_USER_NOTICE wird nicht als Fehler gewertet, aber an allen relevanten Stellen 
     *   mit geloggt. D. h., einziger Loggingunterschied ist, dass der Fehler- und 
     *   http-Responsecode nicht auf 500 sondern auf 200 steht
     *
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline ) {
        $regex = '/^Argument (\d)+ passed to (?:(\w+)::)?([\w{}]+)\(\) must be an instance of (\w+), (\w+) given/';
        if($errno == E_RECOVERABLE_ERROR && preg_match($regex, $errstr, $match)) {
            
            //ensure upwards compatibility to PHP 7 we need this mapping here!
            switch ($match[4]) {
                case 'bool':
                    $match[4] = 'boolean';
                    break;
                case 'int':
                    $match[4] = 'integer';
                    break;
            }
            if($match[4] == $match[5]) {
                return true;
            }
        }
        throw new Zend_Exception($errstr."; File: ".$errfile."; Line: ".$errline."; errno: ".$errno, 0 );
    }
    
    /**
     * @return Gibt debug_backtrace als var_dump in einem String zurück 
     */
    public static function getTrace(){
        $e = new Zend_Exception();
        return $e->getTraceAsString();
    }
}