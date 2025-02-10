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

use MittagQI\ZfExtended\Models\Filter\FilterJoinDTO;

/**
 * Filter to doing just one Join to another table for filtering
 */
class ZfExtended_Models_Filter_Join extends ZfExtended_Models_Filter_JoinAbstract
{
    /**
     * merge the join info into the single filter coming from the frontend
     */
    public function mergeFilter(stdClass $filter)
    {
        //if the type was overwritten by the Join class, we use it. Defaults to the origType
        $filter->type = $this->filterType ?? $filter->_origType;
        if (empty($this->localKey)) {
            $this->localKey = $filter->field;
        }
        $filter->field = $this->searchField;
        //set table name for search field
        $filter->table = $this->table;
    }

    /**
     * Configure the filter instance in the entity
     */
    public function configureEntityFilter(ZfExtended_Models_Filter $filter)
    {
        //if searchfield is ambigious we have to set the originaltable as mapping, the foreign table name is set directly in the filter
        $filter->addTableForField($this->searchField, $filter->getEntityTable());
        $filter->addJoinedTable(new FilterJoinDTO($this->table, $this->localKey, $this->foreignKey, []));
    }
}
