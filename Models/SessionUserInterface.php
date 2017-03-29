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
 * defines the methods needed by a user object to be usable in the session
 */
interface ZfExtended_Models_SessionUserInterface {
    /**
     * sets the user in Zend_Session_Namespace('user')
     *
     * @param string login
     * @return void
     */
    public function setUserSessionNamespaceWithoutPwCheck(string $login);
    
    /**
     * sets the user in the session
     *
     * @param string login
     * @param string passwd unverschlüsseltes Passwort, wie vom Benutzer eingegeben
     * @return void
     */
    public function setUserSessionNamespaceWithPwCheck(string $login, string $passwd);
    
    /**
     * sets the locale of the user
     * @param string $locale
     */
    public function setLocale(string $locale);
    
    /**
     * gets the locale of the user
     * @return string
     */
    public function getLocale();
}
