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
 * converts the given Filter and Sort String from ExtJS to an object structure appliable to a Zend Select Object
 * @author Marc Mittag
 */
class ZfExtended_Models_Filter_ExtJs extends ZfExtended_Models_Filter {
    /**
     * defines the Zend_Db_Select where operation to be used (where / orWhere)
     * @var string
     */
    protected $whereOp = 'where';
    
  /**
   * decodes the filter/sort string, return always an array
   * @param string $todecode
   * @return array
   */
  protected function decode($todecode){
    if(empty($todecode) || $todecode == '[]'){
      return array();
    }
    //if its a array we assume that it was already decoded
    if(is_array($todecode)){
      return $todecode;
    }
    $filters = json_decode($todecode);
    if(empty($filters)) {
        // errors in parsing filters Filterstring: "{filter}"
        throw new ZfExtended_Models_Filter_Exception('E1220',[
            'filter' => $todecode,
        ]);
    }
    foreach ($filters as $filter) {
        if(is_object($filter) && isset($filter->table)) {
            unset($filter->table); //table string may not be set from outside for security reasons!
        }
    }
    return $filters;
  }

  /**
   * clean and apply the sort order to the given select
   */
  protected function applySort(){
    $cleanSort = array();
    foreach($this->sort as $s){
      $dir = strtolower($s->direction);
      $isProperty = $this->entity->hasField($s->property);
      $isMapped = !empty($this->_sortColMap[$s->property]);
      if(($isProperty || $isMapped) && ($dir == 'asc' || $dir == 'desc')){
        $cleanSort[] = $this->mapSort($s->property).' '.$s->direction;
      }
    }
    $this->select->order($cleanSort);
  }

  /**
   * @param stdClass $filter
   * @throws Zend_Exception
   */
  protected function checkAndApplyOneFilter(stdClass $filter){
    $this->initFilterData($filter);
    $this->checkField($filter);
    if(!isset($filter->value) || is_array($filter->value) && empty($filter->value)){
        return;
    }
    $method = 'apply'.ucfirst($filter->type);
    //were assuming that all $methods are using the given field directly as
    //DB field name so we can merge the table alias as simple text
    $field = $filter->field;
    if(!empty($filter->table)) {
        $field = '`'.$filter->table.'`.'.$field;
    }elseif(!empty($this->defaultTable)){
        $field = '`'.$this->defaultTable.'`.'.$field;
    }
    switch($filter->type){
        case 'orExpression':
            $this->applyExpression($filter, true);
            break;
        case 'andExpression':
            $this->applyExpression($filter, false);
            break;
        case 'notIsNull':
            $this->applyNotIsNull($field);
            break;
        case 'isNull':
            $this->applyIsNull($field);
            break;
        case 'percent':
            $method = 'applyPercent_'.$filter->comparison;
            $this->$method($field, $filter->value, $filter->totalField);
            break;
        case 'numeric':
            $method = 'applyNumeric_'.$filter->comparison;
            $this->$method($field, $filter->value);
            break;
        case 'date':
            //to be used for date comparsion "day" based, so additional times are just ignored (in filter and data).
            //for datetime (including the time part) comparsion just use numeric above!
            $method = 'applyDate_'.$filter->comparison;
            $this->$method($field, $filter->value);
            break;
        case 'list':
        case 'notInList':
        case 'listAsString':
        case 'listCommaSeparated':
            settype($filter->value, 'array');
        case 'string':
        case 'boolean':
            $this->$method($field, $filter->value);
            return;
        default:
            //illegal type in filter
            throw new ZfExtended_Models_Filter_Exception('E1221', [
                'type' => $filter->type
            ]);
    }
  }
  
  /**
   * (non-PHPdoc)
   * @see ZfExtended_Models_Filter::applyExpression()
   */
  protected function applyExpression(stdClass $field, $isOr = true) {
      settype($field->value, 'array');
      
      //populate the internal vars
      $select = ZfExtended_Factory::get('Zend_Db_Select', array($this->select->getAdapter()));
      
      $subFilter = ZfExtended_Factory::get(get_class($this), array(
          $this->entity,
          $field->value
      ));
      /* @var $subFilter ZfExtended_Models_Filter_ExtJs */
      $subFilter->whereOp = $isOr ? 'orWhere' : 'where';
      
      //start recursive walk through the OR filters
      $subFilter->applyToSelect($select, false);
      
      $this->where(join(' ',$select->getPart($select::WHERE)));
  }

  /**
   * inits the fields ofthe anonymous filter object
   * @param stdClass $filter
   */
  protected function initFilterData(stdClass $filter) {
      if($filter->type instanceof ZfExtended_Models_Filter_JoinAbstract) {
          $join = $filter->type;
          /* @var $join ZfExtended_Models_Filter_Join */
          $join->mergeFilter($filter);
          $join->configureEntityFilter($this);
      }
    settype($filter->type, 'string');
    settype($filter->field, 'string');
    settype($filter->comparison, 'string');
    //override filter table only if not set explicitly
    if(empty($filter->table) && isset($this->fieldTableMap[$filter->field])) {
        $filter->table = $this->fieldTableMap[$filter->field];
    }
    else {
        settype($filter->table, 'string');
    }
    if(!isset($filter->value)){
      $filter->value = null;
    }
  }

  /**
   * check if field name is valid and field exists in entity
   * @param stdClass $filter
   * @throws Zend_Exception
   */
  protected function checkField(stdClass $filter) {
      $field = $filter->field;
      $isExpression = $filter->type == 'orExpression' || $filter->type == 'andExpression';
      if(isset($filter->type) && $isExpression && empty($field)){
          return;
      }
      if(! preg_match('/[a-z0-9-_]+/i', $field)){
          //Illegal chars in field name "{field}"
          throw new ZfExtended_Models_Filter_Exception('E1222',['field' => $field]);
      }
      if(empty($filter->table) && !$this->entity->hasField($field)){
          //Illegal field "{field}" requested
          throw new ZfExtended_Models_Filter_Exception('E1223',['field' => $field]);
      }
  }

  /**
   * @param string $field
   * @param int $value
   */
  protected function applyNumeric_lt($field, $value) {
    $this->where($field.' < ?', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyNumeric_gt($field, $value) {
    $this->where($field.' > ?', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyNumeric_lteq($field, $value) {
    $this->where($field.' <= ?', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyNumeric_gteq($field, $value) {
    $this->where($field.' >= ?', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyNumeric_eq($field, $value) {
    $this->where($field.' = ?', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyDate_lt($field, $value) {
    $this->where('date('.$field.') < date(?)', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyDate_gt($field, $value) {
    $this->where('date('.$field.') > date(?)', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyDate_lteq($field, $value) {
    $this->where('date('.$field.') <= date(?)', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyDate_gteq($field, $value) {
    $this->where('date('.$field.') >= date(?)', $value);
  }
  /**
   * @param string $field
   * @param int $value
   */
  protected function applyDate_eq($field, $value) {
    $this->where('date('.$field.') = date(?)', $value);
  }
  
  /**
   * apply the the lt percent filter to the select
   * @param string $field  FIXME SQL INJECTION?
   * @param int $value
   * @param string $totalField   FIXME SQL INJECTION?
   */
  protected function applyPercent_lt($field, $value, $totalField) {
      $this->where('IFNULL((('.$field.'/'.$totalField.')*100),0) < ?', $value);
  }
  
  /**
   * apply the the gt percent filter to the select
   * @param string $field
   * @param int $value
   * @param string $totalField
   */
  protected function applyPercent_gt($field, $value, $totalField) {
      $this->where('IFNULL((('.$field.'/'.$totalField.')*100),0) > ?', $value);
  }
  /**
   * apply the eq percent filter to the select
   * @param string $field
   * @param int $value
   * @param string $totalField
   */
  protected function applyPercent_eq($field, $value, $totalField) {
      $this->where('IFNULL((('.$field.'/'.$totalField.')*100),0) = ?', $value);
  }
  
  
  /**
   * @param string $field
   * @param string $value
   */
  protected function applyString($field, $value) {
    $this->where($field.' like ?', '%'.$value.'%');
  }
  /**
   * @param string $field
   */
  protected function applyIsNull($field) {
    $this->where($field.' is null');
  }
  /**
   * @param string $field
   */
  protected function applyNotIsNull($field) {
    $this->where('not '.$field.' is null');
  }

  /**
   * @param string $field
   * @param array $values
   */
  protected function applyList($field, array $values) {
      $this->where($field.' in (?)', $values);
  }
  /**
   * @param string $field
   * @param array $values
   */
  protected function applyNotInList($field, array $values) {
      $this->where($field.' not in (?)', $values);
  }
  
  /**
  * Converts a list filter based on a string search
  *
  * - Filter type does not come from native ExtJs
  * - Filter type is set by mapping
  * @param string $field
  * @param array  $values
  */
  protected function applyListAsString(string $field, array $values) {
      $db = Zend_Registry::get('db');
      $where = array();
      foreach($values as $value){
          $where[] = $db->quoteInto($field.' like ?', '%'.$value.'%');
      }
      $this->where(implode(' OR ', $where));
  }

 /**
  * Converts a list filter based on a string search,
  * where the search values are surrounded with comma
  *
  * - Filter type does not come from native ExtJs
  * - Filter type is set by mapping
  * @param string $field
  * @param array  $values
  */
  protected function applyListCommaSeparated(string $field, array $values){
      //add commas before and after each value
      $this->applyListAsString($field, array_map(function($item){
          return ','.$item.',';
      }, $values));
  }
}