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