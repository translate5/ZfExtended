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

abstract class ZfExtended_Models_Entity_Abstract {

    /**
     * @var Zend_Db_Table_Abstract
     */
    public $db;
    /**
     * maps the Object Attributes (key) to the Row Field Names (value)
     * @var array
     */
    protected $mapping = array();
    /**
     * @var Zend_Db_Table_Row_Abstract
     */
    protected $row;
    /**
     * set the Model_Db_Classname
     * @var string
     */
    protected $dbInstanceClass;
    /**
     * the Validator Classname
     * @var string
     */
    protected $validatorInstanceClass = 'ZfExtended_Models_Validator_Default';
    /**
     * the Validator Instance
     * @var string
     */
    protected $validator;
    /**
     * @var integer
     */
    protected $offset = 0;
    protected $limit = 0;

    /**
     * List of Field Names the set method was called for.
     * @var array
     */
    protected $modified = array();

    /**
     * @var ZfExtended_Models_Filter
     */
    protected $filter;

    public function __construct() {
        $this->db = ZfExtended_Factory::get($this->dbInstanceClass);
        $this->init();
    }

    /**
     * inits the Entity, resets the internal data
     */
    public function init() {
        $this->row = $this->db->createRow();
    }

    /**
     * loads the Entity by Primary Key Id
     * @param integer $id
     */
    public function load($id) {
        try {
            $rowset = $this->db->find($id);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
        }
        if (!$rowset || $rowset->count() == 0) {
            $this->notFound(__CLASS__ . '#PK', $id);
        }
        //load implies loading one Row, so use only the first row
        $this->row = $rowset->rewind()->current();
    }
    /**
     * Fetches one row in an object of type Zend_Db_Table_Row_Abstract
     *
     * Basiert auf fetchRow
     *
     * @param string where OPTIONAL Entspricht dem ersten Parameter einer Zend_Db_Select-Where-Methode
     * @param string whereValue OPTIONAL Entspricht dem zweiten Parameter einer Zend_Db_Select-Where-Methode
     * @param string whereType OPTIONAL Entspricht dem dritten Parameter einer Zend_Db_Select-Where-Methode
     * @param string|array $order OPTIONAL An SQL ORDER clause.
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function loadRow($where=NULL, $whereValue=NULL, $whereType=NULL, $order=NULL) {
        $s = NULL;
        if(!is_null($where)){
            $s = $this->db->select();
            $s->where($where, $whereValue, $whereType);
        }
        $this->row = $this->db->fetchRow($s, $order);
        if(empty($this->row)){
            $this->notFound(__CLASS__ . '#where ', $where);
        }
        return $this->row;
    }
    /**
     * Fetches one row in an object of type Zend_Db_Table_Row_Abstract
     *
     * Basiert auf fetchRow
     *
     * @param Zend_Db_Table_Select 
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function loadRowBySelect(Zend_Db_Table_Select $s) {
        $this->row = $this->db->fetchRow($s);
        if(empty($this->row)){
            $this->notFound(__CLASS__ . '#where ', $where);
        }
        return $this->row;
    }

    /**
     * Throws a Not Found Exception, Parameters: strings to display in Exception Message
     * @param string $key
     * @param string $value
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    protected function notFound($key = '', $value = '') {
        throw new ZfExtended_Models_Entity_NotFoundException("Key: " . $key . '; Value: ' . $value);
    }

    /**
     * loads all Entities out of DB
     * @return array
     */
    public function loadAll() {
        $s = $this->db->select();
        return $this->loadFilterdCustom($s);
    }


    protected function loadFilterdCustom(Zend_Db_Select $s){
      if(!empty($this->filter)) {
        $this->filter->applyToSelect($s);
      }

      if($this->offset || $this->limit) {
        $s->limit($this->limit, $this->offset);
      }
      return $this->db->fetchAll($s)->toArray();
    }

    /**
     * returns the total (without LIMIT) count of rows
     */
    public function getTotalCount(){
      $s = $this->db->select();
      return $this->computeTotalCount($s);
    }

    /**
     * computes the total (without LIMIT) count of rows, applies filters to the given Select Statement
     * @param Zend_Db_Select $s
     * @return integer
     */
    protected function computeTotalCount(Zend_Db_Select $s){
      if(!empty($this->filter)) {
        $this->filter->applyToSelect($s, false);
      }
      $name = $this->db->info(Zend_Db_Table_Abstract::NAME);
      $schema = $this->db->info(Zend_Db_Table_Abstract::SCHEMA);
      $s->from($name, array('numrows' => 'count(*)'), $schema);
      $totalCount = $this->db->fetchRow($s)->numrows;
      $s->reset($s::COLUMNS);
      $s->reset($s::FROM);
      return $totalCount;
    }

    /**
     * saves the Entity to the DB
     *
     * @return mixed  The primary key value(s), as an associative array if the key is compound, or a scalar if the key is single-column.
     */
    public function save() {
        return $this->row->save();
    }

    /**
     * löscht das aktuelle Entity
     */
    public function delete() {
        $this->row->delete();
    }

    /**
     * checks if given data field exists in entity
     * @param string $field
     * @return boolean
     */
    public function hasField($field) {
      return isset($this->row->$field);
    }

    /**
     * Provides the [get|set][Name] Funktions of the Entity, Name is the name of the data field.
     * @param string $name
     * @param array $arguments
     * @throws Zend_Exception
     * @return mixed
     */
    public function __call($name, array $arguments) {
        $method = substr($name, 0, 3);
        $fieldName = lcfirst(substr($this->_getMappedRowField($name), 3));
        switch ($method) {
            case 'get':
                return $this->get($fieldName);
            case 'set':
                if (!isset($arguments[0])) {
                    $arguments[0] = null;
                }
                $this->modified[] = $fieldName;
                return $this->set($fieldName, $arguments[0]);
        }
        throw new Zend_Exception('Method ' . $name . ' not defined');
    }

    /**
     * sets the value of the given data field
     * @param string $name
     * @param mixed $value
     */
    protected function set($name, $value) {
        $field = $this->_getMappedRowField($name);
        $this->row->$field = $value;
    }

    /**
     * returns the value of the given data field
     * @param string $name
     */
    protected function get($name) {
        $field = $this->_getMappedRowField($name);
        return $this->row->$field;
    }

    /**
     * maps the requested Object Attribute name to the underlying DB Field Name
     * @param string $attribute
     * @return string
     */
    protected function _getMappedRowField($attribute) {
        if (!empty($this->mapping[$attribute])) {
            return $this->mapping[$attribute];
        }
        return $attribute;
    }

    /**
     * Magic PHP Function for String Conversion, returns $this as String
     * @return string
     */
    public function __toString() {
        return json_encode($this->getDataObject());
    }

    /**
     * returns $this as data in an stdObject
     * @return stdClass
     */
    public function getDataObject() {
        $data = $this->row->toArray();
        $mapping = array_flip($this->mapping);
        $result = new stdClass();
        foreach ($data as $field => $value) {
            $field = empty($mapping[$field]) ? $field : $mapping[$field];
            $result->$field = $value;
        }
        return $result;
    }

    /**
     * limits the result set of the loadAll Request
     * @param integer $offset
     * @param integer $limit
     */
    public function limit($offset, $limit) {
      $this->offset = $offset;
      $this->limit = $limit;
    }

    /**
     * sets the sort order and filters of the loadAll Result.
     * @param ZfExtended_Models_Filter $filter
     */
    public function filterAndSort(ZfExtended_Models_Filter $filter) {
      $this->filter = $filter;
    }

    /**
     * returns true if all internal set data is valid
     * @return boolean
     */
    public function isValid(){
      return $this->validator->isValid($this->row->toArray());
    }

    /**
     * Throws Exception if data is invalid. Does nothing if all is valid.
     * @todo aktuell wirden die Fehlermeldungen nirgends verwendet. Daher ist die Verarbeitung momentan nur für Debug Zwecke eingerichtet.
     * @throws ZfExtended_ValidateException
     */
    public function validate(){
      $this->validatorLazyInstatiation();
      if(!$this->validator->isValid($this->getModifiedData())) {
        $error = print_r($this->validator->getMessages(), 1);
        throw new ZfExtended_ValidateException($error);
      }
    }

    /**
     * returns an assoc array of the modified fields and values
     * @return array
     */
    protected function getModifiedData() {
      $data = $this->row->toArray();
      $result = array();
      foreach($data as $field => $value) {
        if(in_array($field, $this->modified)){
          $result[$field] = $value;
        }
      }
      return $result;
    }

    protected function validatorLazyInstatiation() {
      if(empty($this->validator)) {
        $this->validator = ZfExtended_Factory::get($this->validatorInstanceClass);
      }
    }

    /**
     * overwrites the default validator
     * @param ZfExtended_Models_Entity_Validator_Abstract $validator
     */
    public function setValidator(ZfExtended_Models_Entity_Validator_Abstract $validator) {
      $this->validator = $validator;
    }

    /**
     * @return ZfExtended_Models_Entity_Validator_Abstract
     */
    public function getValidator() {
      $this->validatorLazyInstatiation();
      return $this->validator;
    }
}
