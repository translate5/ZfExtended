<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

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
    protected $operatorToType = array('like' => 'string', 'in' => 'list');
    
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
        $filter->field = $filter->property;
        unset ($filter->property);
        if (empty ( $this->operatorToType [$filter->operator] )) {
            throw new ZfExtended_Exception ( 'Unkown filter operator from ExtJS 5 Grid Filter!' );
        }
        $filter->type = $this->operatorToType [$filter->operator];
        unset ($filter->operator);
        return $filter;
    }
}