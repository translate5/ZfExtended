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
 */
class ZfExtended_DbConfig_Type_Manager {

    private ZfExtended_DbConfig_Type_CoreTypes $coreTypes;

    /**
     * The mapping of
     * @var array
     */
    private static array $customTypes = [];

    public function __construct() {
        $this->coreTypes = new ZfExtended_DbConfig_Type_CoreTypes();
    }

    /**
     * returns the config type instance or the default type if none configured
     * @param string|null $typeCls
     * @return ZfExtended_DbConfig_Type_Abstract|null
     */
    public function getType(?string $typeCls): ?ZfExtended_DbConfig_Type_Abstract {
        if(empty($typeCls)) {
            return $this->coreTypes;
        }
        if(empty(self::$customTypes[$typeCls])) {
            self::$customTypes[$typeCls] = ZfExtended_Factory::get($typeCls);
        }
        return self::$customTypes[$typeCls];
    }
}
