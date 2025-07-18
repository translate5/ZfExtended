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

abstract class ZfExtended_DbConfig_Type_Abstract
{
    /**
     * validates the given config value (basic type check)
     * @param editor_Models_Config $config the underlying config type
     * @param string $newvalue the value to be checked
     * @param string|null $errorStr OUT the error message of the failed validation
     * @return bool false if not valid
     */
    abstract public function validateValue(editor_Models_Config $config, string &$newvalue, ?string &$errorStr): bool;

    /**
     * returns the GUI view class to be used or null for default handling
     */
    abstract public function getGuiViewCls(): ?string;

    /**
     * converts the config values stored in the DB to the applicable target format
     * @return mixed|string|null
     */
    abstract public function convertValue(string $type, ?string $value);

    /**
     * converts the type of the config value to the corresponding PHP type
     */
    abstract public function getPhpType(string $type): string;

    /**
     * returns true if the given value string is valid regarding the underlying defaults
     */
    abstract public function isValidInDefaults(editor_Models_Config $config, string $value): bool;

    public static function getDefaultList(?string $dbValue): ?string
    {
        return $dbValue;
    }
}
