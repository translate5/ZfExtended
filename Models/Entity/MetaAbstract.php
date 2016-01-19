<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

abstract class ZfExtended_Models_Entity_MetaAbstract extends ZfExtended_Models_Entity_Abstract {
    const META_TYPE_STRING = 'string';
    const META_TYPE_BOOLEAN = 'boolean';
    const META_TYPE_INTEGER = 'integer';
    const META_TYPE_FLOAT = 'float';
    
    public function addMeta($name, $type, $default, $comment, $length = 0) {
        if(! $this->hasField($name)) {
            call_user_func_array(array($this->db, 'addColumn'), func_get_args());
        }
    }
    
    /**
     * Adds an empty meta data rowset to the DB.
     */
    abstract public function initEmptyRowset();
    
    /**
     * Updates the given field in a mutex manner with the given value, but only if the value was other before
     * @param string $field
     * @param string $value
     * @param string $idx
     * @param string $idxField
     * @return boolean returns true if this call caused an update in the DB, false otherwise
     */
    public function updateMutexed($field, $value, $idx, $idxField) {
        return $this->db->update(array($field => $value), array(
                $field.' != ?' => $value,
                $idxField.' = ?' => $idx,
        )) !== 0;
    }
    
    /**
     * Loads all entries by a given value
     * @param string $field
     * @param mixed $value
     * @return array
     */
    public function loadBy($field, $value) {
        $s = $this->db->select()->where($field.' = ?', $value);
        return $this->db->fetchAll($s)->toArray();
    }
}
