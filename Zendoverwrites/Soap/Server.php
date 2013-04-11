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
}