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

/**#@+
 * @author Marc Mittag
 * @package editor
 * @version 1.0
 *
 */
/**
 * Klasse zum Zugriff auf die Tabelle mit Namen des Klassennamens (in lower case)
 */
class ZfExtended_Models_Db_SessionUserLock extends Zend_Db_Table_Abstract {
    protected $_name    = 'sessionUserLock';
    public $_primary = 'login';
    
    /**
     * returns an array of logins of the users already having a session
     */
    public function getLocked() {
        $s = $this->select(array('login'));
        $found = $this->getAdapter()->fetchAll($s);
        return array_map(function($i) {
            return $i['login'];
        }, $found);
    }
}