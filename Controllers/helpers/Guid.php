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

/* * #@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */

/**
 * methods around Guids
 * @deprecated
 */
class ZfExtended_Controller_Helper_Guid extends Zend_Controller_Action_Helper_Abstract {

    /**
     * creates a guid as defined in the config
     * @param bool addBrackets to add {}-brackets around the GUID, defaults to false
     * @return string $guid
     */
    public function create($addBrackets = false) {
        error_log('Called deprecated ZfExtended_Controller_Helper_Guid::create!');
        return ZfExtended_Utils::guid($addBrackets);
    }
}
