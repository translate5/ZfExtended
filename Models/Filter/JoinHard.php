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
 * Searches for data in a separate table, gets the results and use them for filtering in the main table.
 * So no direct join between the tables is made!
 */
class ZfExtended_Models_Filter_JoinHard extends ZfExtended_Models_Filter_JoinAbstract
{
    /**
     * @var stdClass
     */
    protected $filterForBasetable;

    /**
     * @var string
     */
    protected $localFilterType = 'list';

    /**
     * Inits a join config to join a filterable field from a separate table
     * @param string $searchField field to be searched in
     * @param string $foreignKey foreign key in the table
     * @param string $localKey localkey, defaults to searchfield
     */
    public function __construct($tableClass, $searchField, $foreignKey = 'id', $localKey = null, $localFilterType = 'list')
    {
        if (! is_subclass_of($tableClass, Zend_Db_Table_Abstract::class)) {
            //Given tableClass "{tableClass}" is not a subclass of Zend_Db_Table_Abstract!
            throw new ZfExtended_Models_Filter_Exception('E1225', [
                'tableClass' => $tableClass,
            ]);
        }
        $this->localFilterType = $localFilterType;
        parent::__construct($tableClass, $searchField, $foreignKey, $localKey);
    }

    /**
     * merge the join info into the single filter coming from the frontend
     */
    public function mergeFilter(stdClass $filter)
    {
        if (empty($this->localKey)) {
            $this->localKey = $filter->field;
        }
        $this->filterForBasetable = $filter;
    }

    public function configureEntityFilter(ZfExtended_Models_Filter $filterInstance): void
    {
        $db = ZfExtended_Factory::get($this->table);
        /** @var Zend_Db_Table_Abstract $db */

        //clone the filter for the joined table
        $filter = clone $this->filterForBasetable;
        $filter->field = $this->searchField;
        $filter->type = $this->filterForBasetable->_origType; //FIXME only if not remapped here (feature/field missing in constructor!!!!)
        $filter->table = $db->info($db::NAME);

        //reconfigure the filter for the base table to be used as in filter
        $this->filterForBasetable->type = $this->localFilterType;
        $this->filterForBasetable->field = $this->localKey;
        $this->filterForBasetable->value = []; //replaced with data from below

        //init the separate filter instance for the query on the joined table
        $filterInstanceForJoined = ZfExtended_Factory::get(get_class($filterInstance));
        /** @var ZfExtended_Models_Filter $filterInstanceForJoined */
        $filterInstanceForJoined->addFilter($filter);

        //prepare the query to the joined table and fetch data from there
        $select = $db->select()
            ->from($db, [$this->foreignKey]);
        $filterInstanceForJoined->applyToSelect($select);
        $data = $db->fetchAll($select)->toArray();
        if (empty($data)) {
            //an empty value will not be filtered, so we have to provide a non existent ID here to get finally an empty result
            $this->filterForBasetable->value = [0];
        } else {
            $this->filterForBasetable->value = array_column($data, $this->foreignKey);
        }
    }
}
