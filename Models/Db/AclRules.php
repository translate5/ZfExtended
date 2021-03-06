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
 * Klasse zum Zugriff auf die Tabelle mit Namen des Klassennamens (in lower case)
 */
class ZfExtended_Models_Db_AclRules extends Zend_Db_Table_Abstract {
    protected $_name    = 'Zf_acl_rules';
    public $_primary = 'id';
    
    /**
     * Loads all rules by module
     * @param string $module
     * @return array
     */
    public function loadByModule($module){
        return $this->fetchAll($this->select()->where('module = ?', $module))->toArray();
    }
    
    /**
     * returns all available roles
     * @param string $module
     * @return array
     */
    public function loadRoles($module){
        $s = $this->select()
        ->from($this->info(self::NAME), 'role')
        ->where('module = ?', $module)
        ->distinct();
        return array_column($this->fetchAll($s)->toArray(), 'role');
    }
    
    /**
     * returns all available resources
     * @param string $module
     * @return array
     */
    public function loadResources($module){
        $s = $this->select()
        ->from($this->info(self::NAME), 'resource')
        ->where('module = ?', $module)
        ->distinct();
        return array_column($this->fetchAll($s)->toArray(), 'resource');
    }
}