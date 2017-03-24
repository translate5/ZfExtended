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

/**
 * converts the given Filter and Sort String from ExtJS to an object structure appliable to a Zend Select Object
 * @author Marc Mittag
 */
class ZfExtended_Models_Filter_ExtJs5 extends ZfExtended_Models_Filter_ExtJs {
    /**
     * This list contains a mapping between new ExtJS 5 operator parameters (key) 
     * to the old ExtJS 4 type parameters (value)
     * @var array
     */
    protected $operatorToType = array(
            'like' => 'string',
            'notInList' => 'notInList',
            'in' => 'list',
            'eq' => 'numeric',
            'gt' => 'numeric',
            'gteq' => 'numeric',
            'lt' => 'numeric',
            'lteq' => 'numeric',
            '=' => 'boolean',
            '==' => 'boolean',
    );
    
    /**
     * converts the new ExtJS 5 filter format to the old ExtJS 4 format
     * 
     * @param string $todecode
     * @return array
     */
    protected function decode($todecode) {
        $filters = parent::decode ( $todecode );
        foreach ( $filters as $key => $filter ) {
            $filters [$key] = $this->convert ( $filter );
        }
        return $filters;
    }
    
    /**
     * Convertion Method
     * @param stdClass $filter
     * @throws ZfExtended_Exception
     * @return stdClass
     */
    protected function convert(stdClass $filter) {
        //is a sort, do nothing more here
        if(empty($filter->operator) && isset($filter->direction)) {
            return $filter;
        }
        if(!isset($filter->property)){
            return $filter;
        }
        $filter->field = $filter->property;
        unset ($filter->property);
        if (empty ( $this->operatorToType [$filter->operator] )) {
            throw new ZfExtended_Exception ( 'Unkown filter operator from ExtJS 5 Grid Filter!' );
        }
        $filter->type = $this->operatorToType [$filter->operator];
        if($filter->type == 'numeric') {
            $filter->comparison = $filter->operator;
        }
        if($filter->type == 'boolean') {
            $filter->comparison = 'eq';
            $filter->type = 'numeric';
            $filter->value = (isset($filter->value) && $filter->value ? 1 : 0);
        }
        unset ($filter->operator);
        return $filter;
    }
}