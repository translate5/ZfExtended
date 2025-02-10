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

abstract class ZfExtended_Models_Filter_JoinAbstract
{
    protected $table;

    protected $foreignKey;

    protected $searchField;

    protected $filterType;

    protected $localKey;

    /**
     * Inits a join config to join a filterable field from a separate table
     * @param string $table DB table name
     * @param string $searchField field to be searched in
     * @param string $foreignKey foreign key in the table
     * @param string $localKey localkey, defaults to searchfield
     * @param string $type per join overwritable type, defaults to _origType
     */
    public function __construct($table, $searchField, $foreignKey = 'id', $localKey = null, $type = null)
    {
        $this->table = $table;
        $this->searchField = $searchField;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->filterType = $type;
    }

    /**
     * returns the fieldname in which the search should be performed
     * @return string
     */
    public function getSearchfield()
    {
        return $this->searchField;
    }

    /**
     * returns the configured table
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Inour case just checks, if we join the specified table
     */
    public function hasJoinedTable(string $tableName): bool
    {
        return $this->getTable() === $tableName;
    }

    /**
     * merge the join info into the single filter coming from the frontend
     */
    abstract public function mergeFilter(stdClass $filter);

    /**
     * Configure the filter instance in the entity
     */
    abstract public function configureEntityFilter(ZfExtended_Models_Filter $filter);

    /**
     * Debugs the filter
     */
    public function debug(): string
    {
        return json_encode($this->debugFilter(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Debugs our data
     */
    public function debugFilter(): stdClass
    {
        $data = new stdClass();
        $data->_classname = get_class($this);
        $data->table = $this->table;
        $data->searchField = $this->searchField;
        $data->foreignKey = $this->foreignKey;
        $data->localKey = $this->localKey;
        $data->filterType = $this->debugObject($this->filterType);
        if (property_exists($this, 'finalJoin')) {
            $data->finalJoin = $this->debugObject($this->finalJoin);
        }

        return $data;
    }

    public function debugObject(mixed $obj): mixed
    {
        if (is_object($obj) && $obj instanceof ZfExtended_Models_Filter_JoinAbstract) {
            return $obj->debugFilter();
        }
        if (! is_object($obj) || $obj instanceof stdClass) {
            return $obj;
        }

        return get_class($obj);
    }
}
