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
        throw new ZfExtended_Exception('errors in parsing filters Filterstring: '.$todecode."\nURL:".$_SERVER['REQUEST_URI']);
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
        case 'numeric':
        case 'date':
            $method = 'applyNumeric_'.$filter->comparison;
        case 'list':
        case 'notInList':
        case 'listAsString':
        case 'listCommaSeparated':
        case 'string':
        case 'boolean':
            $this->$method($field, $filter->value);
            return;
        default:
            throw new Zend_Exception("illegal type in filter");
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
    if(isset($this->fieldTableMap[$filter->field])) {
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
          throw new Zend_Exception('illegal chars in field name '.$field);
      }
      if(empty($filter->table) && !$this->entity->hasField($field)){
          throw new Zend_Exception('illegal field requested: '.$field);
      }
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
   * @param string $field
   * @param integer $value
   */
  protected function applyNumeric_lt($field, $value) {
    $this->where($field.' < ?', $value);
  }
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyNumeric_gt($field, $value) {
    $this->where($field.' > ?', $value);
  }
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyNumeric_lteq($field, $value) {
    $this->where($field.' <= ?', $value);
  }
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyNumeric_gteq($field, $value) {
    $this->where($field.' >= ?', $value);
  }
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyNumeric_eq($field, $value) {
    $this->where($field.' = ?', $value);
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
   * Setzt einen Listfilter auf Basis einer Stringsuche um,
   * wobei mehrere List-Werte als mit OR kombinierte String-Suchen umgesetzt werden
   *
   * - Filtertyp kommt nicht nativ aus ExtJs
   * - Filtertyp wird durch Mapping gesetzt
   * @param string $field
   * @param array $values
   */
  protected function applyListAsString(string $field, array $values) {
      $this->applayListAsString($field,$values);
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
      $this->applayListAsString($field,$values,true);
  }

  /**
  * Converts a list filter based on a string search, 
  * where the search values are surrounded with comma if $addcoma parametar is true
  * 
  * - Filter type does not come from native ExtJs
  * - Filter type is set by mapping
  * @param string $field
  * @param array  $values
  * @param bool   $addcoma
  */
  private function applayListAsString(string $field, array $values,$addcoma = false){
      $db = Zend_Registry::get('db');
      $where = array();
      foreach($values as $value){
          $where[] = $db->quoteInto($field.' like ?', '%'.($addcoma ? ',':'').$value.($addcoma ? ',':'').'%');
      }
      $this->where(implode(' OR ', $where));
  }
}