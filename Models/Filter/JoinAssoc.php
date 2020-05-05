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
 * Implements a double join for t1 n-n t2 relations with a t3 assoc table in between.
 * Searches in t2 (given as $finalJoin), joins then with $table.
 */
class ZfExtended_Models_Filter_JoinAssoc extends ZfExtended_Models_Filter_Join {
    
    /**
     * The Join definition to the finally joined table
     * @var ZfExtended_Models_Filter_Join
     */
    protected $finalJoin;
    
    /**
     * Inits a join config to join a filterable field from a separate table
     * @param string $table DB table name
     * @param string $searchField field to be searched in
     * @param string $foreignKey foreign key in the table
     * @param string $localKey localkey, defaults to searchfield
     */
    public function __construct($table, ZfExtended_Models_Filter_Join $finalJoin, $foreignKey = 'id', $localKey = null) {
        $this->table = $table;
        $this->finalJoin = $finalJoin;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->searchField = $finalJoin->searchField;
    }
    
    /**
     */
    public function mergeFilter(stdClass $filter) {
        parent::mergeFilter($filter);
        //set table name for search field
        $filter->table = $this->finalJoin->table;
    }
    
    /**
     * Configure the filter instance in the entity
     * @param ZfExtended_Models_Filter $filter
     */
    public function configureEntityFilter(ZfExtended_Models_Filter $filter) {
        //if searchfield is ambigious we have to set the originaltable as mapping, the foreign table name is set directly in the filter
        $filter->addTableForField($this->searchField, $filter->getEntityTable());
        // join to the assoc table
        $filter->addJoinedTable($this->table, $this->localKey, $this->foreignKey, []);
        // join to the final table
        $filter->addJoinedTable($this->finalJoin->table, $this->finalJoin->localKey, $this->finalJoin->foreignKey, [], $this->table);
    }
}