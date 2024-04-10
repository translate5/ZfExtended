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

/*
 * Validiert eine einen Boolean Wert, der mit Json codiert übergeben wurde auf false
 *
 *
 */
class ZfExtended_Validate_JsonBoolFalse extends Zend_Validate_Abstract
{
    public const notBoolean = 'notBoolean';

    public const notFalse = 'notFalse';

    public function __construct()
    {
        $translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        /* @var $translate ZfExtended_Zendoverwrites_Translate */;
        $this->_messageTemplates = [
            self::notBoolean => $translate->_("'%value%' ist nicht vom Typ Boolean"),
            self::notFalse => $translate->_("'%value%' ist nicht FALSE"),
        ];
    }

    public function isValid($value)
    {
        $this->_setValue($value);
        $value = Zend_Json::decode($value);
        if (! is_bool($value)) {
            $this->_error(self::notBoolean);

            return false;
        }
        if ($value) {
            $this->_error(self::notFalse);

            return false;
        }

        return true;
    }
}
