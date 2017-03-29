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
 * Überschreibt den Zend Soap Server, erweitert ihn um das Type Hinting
 */
class  ZfExtended_Zendoverwrites_Soap_Server extends Zend_Soap_Server
{
    /**
     * integriert das ZFExt TypeHinting
     * Throw PHP errors as SoapFaults
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param array $errcontext
     * @return void
     * @throws SoapFault
     */
    public function handlePhpErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
    {
        if($errno == E_USER_ERROR) {
            parent::handlePhpErrors($errno, $errstr, $errfile, $errline, $errcontext);
            return;
        }
        ZfExtended_Resource_ErrorHandler::errorHandler($errno, $errstr, $errfile, $errline);
    }
    
    /**
     * Der Soap Server fängt nur E_USER_ERRORs ab, muss aber für die Integration des ZfExt ErrorHandlers alle abfangen
     *
     * @return boolean display_errors original value
     */
    protected function _initializeSoapErrorContext()
    {
      $displayErrorsOriginalState = ini_get('display_errors');
      ini_set('display_errors', false);
      set_error_handler(array($this, 'handlePhpErrors'));
      return $displayErrorsOriginalState;
    }
    
    public function fault($fault = null, $code = "Receiver") {
        $log = ZfExtended_Factory::get('ZfExtended_Log');
        /* @var $log ZfExtended_Log */
        $log->logError("Error in processing SOAP request: ".$fault);
        return parent::fault($fault, $code);
    }
}