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

declare(strict_types=1);

/**
 * Contains the config handler for a simple map (key: type string => value: as configured)
 * Intended to be overwritten for more specific.
 */
class ZfExtended_DbConfig_Type_SimpleMap extends ZfExtended_DbConfig_Type_CoreTypes
{
    /**
     * returns the GUI view class to be used or null for default handling
     */
    public function getGuiViewCls(): ?string
    {
        return 'Editor.view.admin.config.type.SimpleMap';
    }

    public function validateValue(editor_Models_Config $config, &$newvalue, ?string &$errorStr): bool
    {
        $rawType = parent::validateValue($config, $newvalue, $errorStr);

        // if the raw type is not correct fail validation
        if (! $rawType) {
            return false;
        }

        $err = '';

        //from parent validate we still get a string
        $confVal = (array) $this->jsonDecode($newvalue, $err);

        //sort by the keys, from the lowest to the biggest
        ksort($confVal);

        return true;
    }
}
