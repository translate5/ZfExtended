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
