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
 * Generates gender-specific salutation in emails sent to the logged-in employee.
 *
 * @param string $gender (optional) If not set, it will use the gender of the currently logged-in employee.
 *                       If set, only the values 'm' and 'f' are allowed.
 * @return string Salutation
 */
class ZfExtended_View_Helper_MailEmployeeSalutation extends Zend_View_Helper_Abstract
{
    public function mailEmployeeSalutation($gender = false)
    {
        if (! $gender) {
            $gender = ZfExtended_Authentication::getInstance()->getUser()->getGender();
        } elseif ($gender != 'f' && $gender != 'm' && $gender != 'n') {
            throw new Zend_Exception('$gender hat den nicht erwarteten Wert ' . $gender, 0);
        }
        if ($gender == 'f') {
            return $this->view->translate->_('Sehr geehrte Frau');
        }
        if ($gender == 'm') {
            return $this->view->translate->_('Sehr geehrter Herr');
        }

        return $this->view->translate->_('Sehr geehrte(r)');
    }
}
