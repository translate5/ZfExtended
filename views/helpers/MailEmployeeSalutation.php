<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Erzeugt in Mails an den eingeloggten Mitarbeiter die geschlechtsspezifische
 * Anrede
 *
 * @param string $gender optional. Falls nicht gesetzt wird auf das gender des
 *      aktuell eingeloggten Employee zurückgegriffen. Falls gesetzt sind nur die
 *      Werte 'm' und 'f' erlaubt.
 * @return string salutation
 *
 */

class ZfExtended_View_Helper_MailEmployeeSalutation extends Zend_View_Helper_Abstract
{
    public function mailEmployeeSalutation($gender = false,$userLocale=false)
    {
        if(!$gender){
            $user = new Zend_Session_Namespace('user');
            $gender = $user->data->gender;
        }
        elseif($gender !='f' and $gender!='m'){
            throw new Zend_Exception('$gender hat den nicht erwarteten Wert '.$gender, 0);
        }
        return ($gender == 'f')?
        $this->view->translate->_('Sehr geehrte Frau',$userLocale):
        $this->view->translate->_('Sehr geehrter Herr',$userLocale);
    }
}