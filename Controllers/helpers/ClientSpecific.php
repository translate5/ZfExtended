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
 * Encapsulates some layout client specific helper functions
 */
class ZfExtended_Controller_Helper_ClientSpecific extends Zend_Controller_Action_Helper_Abstract {
    public function getCss($app): ?string {
        $app .= '.css';
        if(file_exists(APPLICATION_ROOT.'/public/client-specific/'.$app)) {
            $version = '?v='.ZfExtended_Utils::getAppVersion();
            return APPLICATION_RUNDIR . '/client-specific/'. $app . $version;
        }
        return null;
    }
}
