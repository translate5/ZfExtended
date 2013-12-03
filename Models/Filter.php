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
 * @todo bei Bedarf aus der Unterklasse ExtJS abstrahieren
 */
abstract class ZfExtended_Models_Filter{
  /**
   * The entity the filter will be applied to
   * @var ZfExtended_Models_Entity_Abstract
   */
  protected $entity;
  /**
   * The Filter array
   * @var array
   */
  protected $filter = array();
  /**
   * The Sort array
   * @var array
   */
  protected $sort = array();
  /**
   * the select statement to apply the filters
   * @var Zend_Db_Select
   */
  protected $select;
  /**
   * mappt zu sortierende Spalten auf eine Spalte, nach der statt der übergebenen
   * Spalte sortiert werden soll (key = übergebene Spalte, value = Spalte, nach
   * der sortiert werden soll)
   * @var array
   */
  protected $_sortColMap = array();
  /**
   * mappt einen eingehenden Filtertyp auf einen anderen Filtertyp für ein bestimmtes
   * Feld.
   * @var array array($field => array(origType => newType))
   */
  protected $_filterTypeMap = NULL;
  
  /**
   * default table prefix to be used, if its set
   * @var string
   */
  protected $defaultTable = null;
  
  /**
   * fields can be mapped to table / table prefixes to be used
   * @var array
   */
  protected $fieldTableMap = array();
  
  /**
   * @param ZfExtended_Models_Entity_Abstract $entity
   * @param string $filter
   * @param string $defaultFilter
   * @param string $sort
   * @param array|NULL $sortColMap
   * @param array|NULL $filterTypeMap
   */
  public function __construct(ZfExtended_Models_Entity_Abstract $entity, $filter, 
                  $defaultFilter = null, $sort = null, $sortColMap = NULL, $filterTypeMap = NULL){
    $this->entity = $entity;
    $this->sort = $this->decode($sort);
    settype($this->sort, 'array');
    $this->_sortColMap = $sortColMap;
    $this->_filterTypeMap = $filterTypeMap;
    $this->filter = $this->decode($filter);
    $this->mergeAdditionaFilters($this->decode($defaultFilter));
    settype($this->filter, 'array');
    $this->init();
  }
  
  /**
   * merges the additional default filters to the internal filter array
   */
  protected function mergeAdditionaFilters(array $defaultFilters) {
      $this->filter = array_merge($this->filter, $defaultFilters);
  }
  
  /**
   * applies the filter and sort statements to the given select and return it
   * @param Zend_Db_Select $select
   * @param boolean $applySort [optional] default true
   * @return Zend_Db_Select
   */
  public function applyToSelect(Zend_Db_Select $select, $applySort = true) {
    $this->select = $select;
    if($applySort){
        $this->applySort();
    }
    foreach($this->filter as $filter){
      $this->checkAndApplyOneFilter($filter);
    }
    return $this->select;
  }
  
  protected function init() {
    $this->mapFilter();
  }
  
  /**
   * sets the default table prefix to be used for sorting and filtering, 
   * needed for example on joining tables
   * @param string $table
   */
  public function setDefaultTable(string $table) {
      $this->defaultTable = $table;
  }
  
  /**
   * mappt die Filter anhand $this->_filterTypeMap
   */
  protected function mapFilter(){
      if(!empty($this->_filterTypeMap)){
          foreach($this->_filterTypeMap as $field => $origType){
                $typeMap = each($origType);
                foreach($this->filter as &$filter){
                  if($filter->field === $typeMap['key'] and isset($typeMap['value'][$filter->type])){
                    $filter->type = $typeMap['value'][$filter->type];
                  }
                }
          }
      }
  }

  /**
   * returns true if sort info is given
   * @return boolean
   */
  public function hasSort(){
    return !empty($this->sort);
  }
  
  /**
   * returns true if filter info is given
   * @return boolean
   */
  public function hasFilter(){
    return !empty($this->filter);
  }
  
  /**
   * adds a field to the sortlist  
   * @param string $field
   * @param boolean $desc [optional] per default sort ASC, if true here sort DESC 
   * @param boolean $prepend [optional] per default add field to the end of fieldlist to sort after. set to true to prepend the field to the beginning of the list
   */
  public function addSort($field, $desc = false, $prepend = false){
    $sort = new stdClass();
    $sort->direction = $desc ? 'desc' : 'asc';
    $sort->property = $this->mapSort($field);
    
    if($prepend){
      array_unshift($this->sort, $sort);
    }
    else {
      $this->sort[] = $sort;
    }
  }
  
  /**
   * mappt einen gegebenen String auf sein Mapping in $this->_sortColMap, so vorhanden
   * @param string sortKey
   * @return string sortKey
   */
  protected function mapSort(string $sortKey){
      $defaultTable = empty($this->defaultTable) ? '' : '`'.$this->defaultTable.'`.';
      if (isset($this->_sortColMap[$sortKey])) {
          return $defaultTable.$this->_sortColMap[$sortKey];
      }
      return $defaultTable.$sortKey;
  }

  public function addTableForField($field, $table) {
      $this->fieldTableMap[$field] = $table;
  }
  
  /**
   * decodes the filter/sort string, return always an array
   * @param string $todecode
   * @return array
   */
  abstract protected function decode($todecode);
  
  /**
   * applies the given filter object to the internal select statement
   * @param stdClass $filter
   * @throws Zend_Exception
   */
  abstract protected function checkAndApplyOneFilter(stdClass $filter);
  
  /**
   * applies the data in the internal sort array to the internal select statement
   */
  abstract protected function applySort();
  
  /**
   * provide a way to produce parenthesized OR where statements like: 
   * where foo and (bar OR baz)
   * @param stdClass $filter
   */
  abstract protected function applyOrExpression(stdClass $filter);
}
