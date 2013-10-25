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
class ZfExtended_Models_Filter_ExtJs extends ZfExtended_Models_Filter{
  /**
   * decodes the filter/sort string, return always an array
   * @param string $todecode
   * @return array
   */
  protected function decode($todecode){
    if(empty($todecode)){
      return array();
    }
    return json_decode($todecode);
  }

  /**
   * clean and apply the sort order to the given select
   */
  protected function applySort(){
    $cleanSort = array();
    foreach($this->sort as $s){
      $dir = strtolower($s->direction);
      if($this->entity->hasField($s->property) && ($dir == 'asc' || $dir == 'desc')){
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
    $this->checkField($filter->field);
    if(!isset($filter->value) || is_array($filter->value) && empty($filter->value)){
        return;
    }
    $method = 'apply'.ucfirst($filter->type);
    //were assuming that all $methods are using the given field directly as 
    //DB field name so we can merge the table alias as simple text
    $field = $filter->field;
    if(!empty($filter->table)) {
        $field = '`'.$filter->table.'`.'.$field;
    }
    switch($filter->type){
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
        case 'listAsString':
        case 'string':
        case 'boolean':
            $this->$method($field, $filter->value);
            return;
        default:
            throw new Zend_Exception("illegal type in filter");
    }
  }

  /**
   * inits the fields ofthe anonymous filter object
   * @param stdClass $filter
   */
  protected function initFilterData(stdClass $filter) {
    settype($filter->type, 'string');
    settype($filter->field, 'string');
    settype($filter->comparison, 'string');
    settype($filter->table, 'string');
    if(!isset($filter->value)){
      $filter->value = null;
    }
  }

  /**
   * check if field name is valid and field exists in entity
   * @param string $field
   * @throws Zend_Exception
   */
  protected function checkField($field) {
    if(! preg_match('/[a-z0-9-_]+/i', $field)){
      throw new Zend_Exception('illegal chars in field name');
    }
    if(!$this->entity->hasField($field)){
      throw new Zend_Exception('illegal field requested');
    }
  }

  /**
   * @param string $field
   * @param array $values
   */
  protected function applyList($field, array $values) {
    $this->select->where($field.' in (?)', $values);
  }
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyNumeric_lt($field, $value) {
    $this->select->where($field.' < ?', $value);
  }
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyNumeric_gt($field, $value) {
    $this->select->where($field.' > ?', $value);
  }
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyNumeric_eq($field, $value) {
    $this->select->where($field.' = ?', $value);
  }
  /**
   * @param string $field
   * @param string $value
   */
  protected function applyString($field, $value) {
    $this->select->where($field.' like ?', '%'.$value.'%');
  }
  /**
   * @param string $field
   */
  protected function applyIsNull($field) {
    $this->select->where($field.' is null');
  }
  /**
   * @param string $field
   */
  protected function applyNotIsNull($field) {
    $this->select->where('not '.$field.' is null');
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
      $db = Zend_Registry::get('db');
      $where = array();
      foreach($values as $value){
        $where[] = $db->quoteInto($field.' like ?', '%'.$value.'%');
        $where[] = ' OR ';
      }
      array_pop($where);
      $this->select->where(implode('', $where));
  }
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyBoolean($field, $value) {
    if($value){
      $this->select->where($field);
    }
    else {
      $this->select->where('!'.$field);
    }
  }
}
