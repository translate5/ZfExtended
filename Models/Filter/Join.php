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

/**
 */
class ZfExtended_Models_Filter_Join extends ZfExtended_Models_Filter_JoinAbstract {
    /**
     * merge the join info into the single filter coming from the frontend
     * @param stdClass $filter
     */
    public function mergeFilter(stdClass $filter) {
        $filter->type = $filter->_origType; //FIXME only if not remapped here (feature/field missing in constructor!!!!)
        if(empty($this->localKey)) {
            $this->localKey = $filter->field;
        }
        $filter->field = $this->searchField;
    }
    
    /**
     * Configure the filter instance in the entity
     * @param ZfExtended_Models_Filter $filter
     */
    public function configureEntityFilter(ZfExtended_Models_Filter $filter) {
        $filter->addTableForField($this->searchField, $this->table);
        $on = '`'.$this->localKey.'` = `'.$this->foreignKey.'`';
        $filter->addJoinedTable($this->table, $this->localKey, $this->foreignKey, [$this->searchField]);
    }
}