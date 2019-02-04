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
 * @todo bei Bedarf aus der Unterklasse ExtJS abstrahieren
 */
abstract class ZfExtended_Models_Filter {
    
    const SORT_ASC = 'asc';
    const SORT_DESC = 'desc';
    
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
   * Contains the automatically joined tables coming from join configurations in fileTypeMaps 
   * @var array
   */
  protected $joinedTables = [];
  
  /**
   * @param ZfExtended_Models_Entity_Abstract $entity optional, needed for default invocation in controller
   * @param string $filter optional, needed for default invocation in controller
   */
  public function __construct(ZfExtended_Models_Entity_Abstract $entity = null, $filter = null){
    $this->entity = $entity;
    if(!empty($filter)) {
        $this->filter = $this->decode($filter);
        settype($this->filter, 'array');
    }
  }
  
  /**
   * for real cloning we have to clone our sort and filter fields (which contain objects) also
   */
  public function __clone() {
      foreach ($this->sort as &$sort) { 
          $sort = clone $sort; 
      }
      foreach ($this->filter as &$filter) { 
          $filter = clone $filter; 
      }
  }

  /**
   * sets an additional filter
   * can be a default filter in addition to the user set filter
   * @param string $defaultFilter additional filter string to be appended
   */
  public function setDefaultFilter($defaultFilter){
    $this->mergeAdditionalFilters($this->decode($defaultFilter));
  }
  
  
  /**           
   * Adds an additional filter in internal defined (ext4) format <BR/>
   * Ext4 Filter Object Example: <BR/>
   * {<BR/>
   *   <b>type:</b> &emsp;numeric | boolean | string | notInList | list| numeric| numeric | numeric | numeric | numeric <BR/>
   *   <b>comparison:</b>   eq    |    =    | like   | notInList |  in |   eq   |   gt    |   gteq  |    lt   |  lteq   <BR/>
   * }
   * @param stdClass $filter
   */
  public function addFilter(stdClass $filter) {
      $this->filter[] = $filter;
  }
  
  /***
   * Return all filters as object array
   */
  public function getFilters(){
      return $this->filter;
  }
  
  /**
   * Remouves filter by filter name ($filter->field)
   * @param filterName
   * @return boolean
   */
  public function deleteFilter($filterName) {
      //checking for a specific filtered field
      foreach($this->filter as $index => $filter) {
          if($filter->field === $filterName) {
              unset($this->filter[$index]);
              return true;
          }
      }
      return false;  
  }
  
  /**
   * sort string
   * @param string $sort
   */
  public function setSort($sort) {
      $this->sort = $this->decode($sort);
      settype($this->sort, 'array');
  }
  
  /**
   * returns the current sort
   * @return multitype:
   */
  public function getSort() {
      return $this->sort;
  }
  
  /**
   * sets several field mappings (field name in frontend differs from that in backend)
   * should be called after setDefaultFilter
   * @param array|NULL $sortColMap
   * @param array|NULL $filterTypeMap
   */
  function setMappings($sortColMap = null, $filterTypeMap = null) {
    $this->_sortColMap = $sortColMap;
    $this->_filterTypeMap = $filterTypeMap;
    $this->mapFilter();
  }
  
  /**
   * merges the additional default filters to the internal filter array
   */
  protected function mergeAdditionalFilters(array $defaultFilters) {
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
    $from = $select->getPart($select::FROM);
    if(empty($from) && !empty($this->joinedTables)) {
        //we have to assemble here to add the right table as default table, otherwise the joined table is used, which is wrong
        $select->assemble();
        $from = $select->getPart($select::FROM);
    }
    if(empty($this->defaultTable)) {
        $table = reset($from)['tableName'];
    }
    else {
        $table = $this->defaultTable;
    }
    foreach($this->joinedTables as $config) {
        if(count($config) > 4) {
            list($foreignTable, $localKey, $foreignKey, $columns, $localTable) = $config;
        }
        else {
            list($foreignTable, $localKey, $foreignKey, $columns) = $config;
            $localTable = $table;
        }
        $select->join($foreignTable, '`'.$localTable.'`.`'.$localKey.'` = `'.$foreignTable.'`.`'.$foreignKey.'`', $columns);
        $select->setIntegrityCheck(false);
    }
    return $this->select;
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
   * returns true if the filter has already configured a default table
   * @return boolean
   */
  public function hasDefaultTable() {
      return !empty($this->defaultTable);
  }
  
  /**
   * mappt die Filter anhand $this->_filterTypeMap
   */
  protected function mapFilter(){
      if(empty($this->_filterTypeMap)){
          return;
      }
      foreach($this->filter as &$filter){
          //check if there is a type map for the current filter
          if(empty($this->_filterTypeMap[$filter->field])) {
              continue;
          }
          $typeMap = $this->_filterTypeMap[$filter->field];
          //check if in the current type map there is a mapping for the current type
          if(empty($typeMap[$filter->type])) {
              continue;
          }
          
          $filter->_origType = $filter->type;
          $filter->type = $typeMap[$filter->type];
      }
  }

  /**
   * returns true if sort info is given
   * @param string $fieldName optional, if given checks if a sort for the given original fieldName is set
   * @return boolean
   */
  public function hasSort($fieldName = false){
      if($fieldName === false) {
          return !empty($this->sort);
      }
      //checking for a specific sorted field
      foreach($this->sort as $sort) {
          if($sort->property === $fieldName) {
              return true;
          }
      }
      return false;
  }
  
  /**
   * returns true if filter info is given
   * @param string $fieldName optional, if given checks if a filter for the given original fieldName is set
   * @param object $foundFilter optional, is a reference, will be populated with the found filter (if a name was given)
   * @return boolean
   */
  public function hasFilter($fieldName = false, & $foundFilter = null){
      if($fieldName === false) {
        return !empty($this->filter);
      }
      //checking for a specific filtered field
      foreach($this->filter as $filter) {
          if($filter->field === $fieldName) {
              $foundFilter = $filter;
              return true;
          }
      }
      return false;
  }
  
  /**
   * adds a field to the sortlist  
   * @param string $field
   * @param boolean $desc [optional] per default sort ASC, if true here sort DESC 
   * @param boolean $prepend [optional] per default add field to the end of fieldlist to sort after. set to true to prepend the field to the beginning of the list
   */
  public function addSort($field, $desc = false, $prepend = false){
    $sort = new stdClass();
    $sort->direction = $desc ? self::SORT_DESC : self::SORT_ASC;
    $sort->property = $this->mapSort($field);
    
    if($prepend){
      array_unshift($this->sort, $sort);
    }
    else {
      $this->sort[] = $sort;
    }
  }
  
  /**
   * Swaps the sorting direction of the currently stored order
   */
  public function swapSortDirection() {
      if(empty($this->sort)) {
          return;
      }
      foreach($this->sort as $sort) {
          $sort->direction = ($sort->direction == self::SORT_ASC ? self::SORT_DESC : self::SORT_ASC);
      }
  }
  
  /**
   * mappt einen gegebenen String auf sein Mapping in $this->_sortColMap, so vorhanden
   * @param string sortKey
   * @return string sortKey
   */
  public function mapSort(string $sortKey){
      $origSortkey = $sortKey;
      if (isset($this->_sortColMap[$sortKey])) {
          $sortKey = $this->_sortColMap[$sortKey];
      }
      //if the mapped sortkey is a joined table, we have to configure it
      if($sortKey instanceof ZfExtended_Models_Filter_JoinAbstract) {
          $sortKey->configureEntityFilter($this);
          return $sortKey->getTable().'.'.$sortKey->getSearchfield();
      }
      if(isset($this->fieldTableMap[$origSortkey])) {
          return $this->fieldTableMap[$origSortkey].'.'.$sortKey;
      }
      $defaultTable = empty($this->defaultTable) ? '' : $this->defaultTable.'.';
      return $defaultTable.$sortKey;
  }

  /**
   * Adds a table alias for the usage of the given field (does not add automatically the join)
   * @param string $field
   * @param string $table
   */
  public function addTableForField($field, $table) {
      $this->fieldTableMap[$field] = $table;
  }
  
  /**
   * Adds a table join configuration to be used for the filters
   * @param string $table
   * @param string $localKey
   * @param string $foreignKey
   * @param array $columns
   */
  public function addJoinedTable($table, $localKey, $foreignKey, array $columns = [], $localOverride = null) {
      $this->joinedTables[$table.'#'.$localKey.'#'.$foreignKey] = func_get_args();
  }
  
  /**
   * returns the original table name of the underlying entity
   * @return string
   */
  public function getEntityTable() {
      $db = $this->entity->db;
      return $db->info($db::NAME);
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
   * provide a way to produce parenthesized OR/AND where statements like: 
   * where foo and (bar OR (baz AND bof))
   * @param stdClass $filter
   * @param boolean $isOr defines if is a OR or an AND expression (if param is false)
   */
  abstract protected function applyExpression(stdClass $filter, $isOr = true);
  
  /**
   * @param string $field
   * @param integer $value
   */
  protected function applyBoolean($field, $value) {
    if($value){
      $this->where($field);
    }
    else {
      $this->where('!'.$field);
    }
  }
  
  /**
   * This methods encapsualtes Zend_Db_Select::where and orWhere
   * @param string $cond
   * @param mixed $value
   * @param int $type
   */
  protected function where($cond, $value = null, $type = null) {
      $where = $this->whereOp;
      $this->select->$where($cond, $value, $type);
  }
}
