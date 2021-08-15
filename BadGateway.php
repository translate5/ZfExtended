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

/**
 * Bad Gateway means: Our System is OK, but the requested third party systems gives an error.
 * @deprecated
 */
class ZfExtended_BadGateway extends ZfExtended_Exception {
    protected $defaultCode = 502;
    protected $defaultMessage = 'Das angefragte Zielsystem antwortete mit einem Fehler. Fehlermeldung des Zielsystems:';
    protected $defaultMessageTranslate = true;
    
    public function __toString(){
        $msg = $this->message;
        $this->message .= "\n".print_r($this->errors,1);
        $res = parent::__toString();
        $this->message = $msg;
        return $res;
    }
}