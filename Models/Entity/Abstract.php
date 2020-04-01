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

abstract class ZfExtended_Models_Entity_Abstract {
    /**
     * @var string
     */
    const VERSION_FIELD = 'entityVersion';
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
     * Additional table class used to save the data content in other table.
     * Change the table with setTable($dbWritable) if the entity should be saved in the table defined
     * in the $dbWritable
     * @var Zend_Db_Table_Abstract
     */
    public $dbWritable;
    
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
     * List of Field Values overwritten by setting a new value
     * @var array
     */
    protected $modifiedValues = array();

    /**
     * @var ZfExtended_Models_Filter
     */
    protected $filter;
    
    /**
     * @var ZfExtended_EventManager
     */
    protected $events = false;
    
    /**
     * contains the name of the relating database-table
     * @var string
     */
    protected $tableName;

    
    public function __construct() {
        $this->db = ZfExtended_Factory::get($this->dbInstanceClass);
        $this->events = ZfExtended_Factory::get('ZfExtended_EventManager', array(get_class($this)));
        $this->init();
        $db = $this->db;
        $this->tableName = $db->info($db::NAME);
    }

    /**
     * inits the Entity, resets the internal data
     * if data object is given, use this values.
     * If $assumeDatabase we "assume" that the given data really already exists in database.
     * @param array $data
     * @param bool $assumeDatabase
     */
    public function init(array $data = null, $assumeDatabase = false) {
        if(empty($data)) {
            $this->row = $this->db->createRow();
        }
        else {
            $this->row = $this->db->createRow($data);
            if ($assumeDatabase) {
                $this->row->refresh();
            }
        }
    }

    /**
     * Deep Cloning of the internal data object
     * else all cloned objects will only have a reference to the same $this->rows
     */
    public function __clone() {
        $this->row = clone $this->row;
    }
    
    
    /**
     * loads the Entity by Primary Key Id
     * @param int $id
     */
    public function load($id) {
        try {
            $rowset = $this->db->find($id);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
        }
        if (!$rowset || $rowset->count() == 0) {
            $this->notFound('#PK', $id);
        }
        //load implies loading one Row, so use only the first row
        return $this->row = $rowset->rewind()->current();
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
     * @return Zend_Db_Table_Row_Abstract
     */
    public function loadRow($where=NULL, $whereValue=NULL, $whereType=NULL, $order=NULL) {
        $s = NULL;
        if(!is_null($where)){
            $s = $this->db->select();
            $s->where($where, $whereValue, $whereType);
        }
        $this->row = $this->db->fetchRow($s, $order);
        if(empty($this->row)){
            $this->notFound('#where '.$where, $whereValue);
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
            $this->notFound('#bySelect', $s);
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
        $cls = explode('_', get_class($this));
        throw new ZfExtended_Models_Entity_NotFoundException(end($cls)." Entity Not Found: Key: " . $key . '; Value: ' . $value);
    }

    /**
     * loads all Entities out of DB
     * @return array
     */
    public function loadAll() {
        $s = $this->db->select();
        return $this->loadFilterdCustom($s);
    }


    /**
     * @param Zend_Db_Select $s
     * @return array
     */
    protected function loadFilterdCustom(Zend_Db_Select $s){
        $this->applyFilterAndSort($s);
        return $this->db->fetchAll($s)->toArray();
    }
    
    /***
     * apply the filter and sort to the select query
     * @param Zend_Db_Select $s
     */
    protected function applyFilterAndSort(Zend_Db_Select &$s){
        if(!empty($this->filter)) {
            $this->filter->applyToSelect($s);
        }
        
        if($this->offset || $this->limit) {
            $s->limit($this->limit, $this->offset);
        }
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

      $from = $s->getPart($s::FROM);
      if(empty($from[$name])) {
          $s->from($name, array('numrows' => 'count(*)'), $schema);
      }
      else {
          $s->reset($s::COLUMNS);
          $s->columns(array('numrows' => 'count(*)'));
      }
      $totalCount = $this->db->fetchAll($s);
      $s->reset($s::COLUMNS);
      $s->reset($s::FROM);
      return count($totalCount);
    }

    /**
     * saves the Entity to the DB
     * @return mixed  The primary key value(s), as an associative array if the key is compound, or a scalar if the key is single-column.
     */
    public function save() {
        $this->events->trigger("beforeSave", $this, array(
                'entity' => $this,
        ));
        try {
            return $this->row->save();
        }
        catch (Zend_Db_Statement_Exception $e) {
            $this->handleIntegrityConstraintException($e);
        }
    }
    
    /**
     * löscht das aktuelle Entity
     */
    public function delete() {
        try {
            $this->row->delete();
        }
        catch (Zend_Db_Statement_Exception $e) {
            $this->handleIntegrityConstraintException($e);
        }
    }

    /**
     * Handles DB Exceptions: encapsualates Integrity constraint violation into separate expcetions, all others are thrown directly
     */
    protected function handleIntegrityConstraintException(Zend_Db_Statement_Exception $e) {
        $msg = $e->getMessage();
        if(strpos($msg, 'Integrity constraint violation:') === false) {
            throw $e;
        }
        if(strpos($msg, '1062 Duplicate entry') !== false) {
            throw new ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey('E1015', [
                'entity' => get_class($this),
                'data' => $this->getDataObject(),
            ], $e);
        }
        
        $is1451 = strpos($msg, '1451 Cannot delete or update a parent row: a foreign key constraint fails') !== false;
        $is1452 = strpos($msg, '1452 Cannot add or update a child row: a foreign key constraint fails') !== false;
        if($is1451 || $is1452) {
            throw new ZfExtended_Models_Entity_Exceptions_IntegrityConstraint('E1016', [
                'entity' => get_class($this),
                'data' => $this->getDataObject(),
            ], $e);
        }
        throw $e;
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
        if($name == 'get' || $name == 'set') {
            throw new Zend_Exception('Method ' . $name . ' is trapped by call but it is a protected function. use __call('.$name.'.ucfirst($field)) instead!');
        }
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
                if(!array_key_exists($fieldName, $this->modifiedValues)) {
                    //presave old value
                    $this->modifiedValues[$fieldName] = $this->get($fieldName);
                }
                return $this->set($fieldName, $arguments[0]);
        }
        throw new Zend_Exception('Method ' . $name . ' not defined');
    }

    /**
     * sets the entity version to be compared against
     * @param int $version
     */
    public function setEntityVersion($version) {
        if($this->hasField(self::VERSION_FIELD)) {
            //sets the version to be compared as entitiy value, is evaluated by trigger
            $this->__call(__FUNCTION__, array($version));
        }
        else {
            //sets the version to be compared as mysql var, is evaluated by trigger
            $this->db->getAdapter()->query('SET @`'.self::VERSION_FIELD.'` := '.(int)$version.';');
        }
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
     * @param int $offset
     * @param int $limit
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
     * returns the internal configured filter
     * @return ZfExtended_Models_Filter
     */
    public function getFilter() {
        return $this->filter;
    }
    
    /**
     * returns true if all internal set data is valid
     * @return boolean
     */
    public function isValid(){
        $this->validatorLazyInstatiation();
        return $this->validator->isValid($this->row->toArray());
    }

    /**
     * Throws Exception if data is invalid. Does nothing if all is valid.
     * @throws ZfExtended_ValidateException
     */
    public function validate(){
        $this->validatorLazyInstatiation();
        if(!$this->validator->isValid($this->getModifiedData())) {
            //TODO the here thrown exception is the legacy fallback. 
            // Each Validator should implement an own isValid which throws a UnprocessableEntity Exception it self.
            // See Segment Validator for an example
            $errors = $this->validator->getMessages();
            $error = print_r($errors, 1);
            $e = new ZfExtended_ValidateException($error);
            $e->setErrors($errors);
            throw $e;
        }
    }

    /**
     * returns an assoc array of the modified fields and (new) values
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

    /**
     * returns true if entity was modified since last load by a setter.
     * if fieldname is given as parameter, check this field only
     * @param string $field optional, if given check field only
     * @return boolean
     */
    public function isModified($field = null) {
        if(empty($field)) {
            return !empty($this->modified);
        }
        return in_array($field, $this->modified);
    }
    
    /**
     * returns the value of an attribute before modified, if not modified return actual value
     */
    public function getOldValue($field) {
        if($this->isModified($field)) {
            return $this->modifiedValues[$field];
        }
        return $this->get($field);
    }
    
    /**
     * returns the modified values (the old values)
     * @return array
     */
    public function getModifiedValues() {
        return $this->modifiedValues;
    }
    
    protected function validatorLazyInstatiation() {
        if(empty($this->validator)) {
            $this->validator = ZfExtended_Factory::get($this->validatorInstanceClass, [$this]);
        }
    }

    /**
     * overwrites the default validator
     * @param ZfExtended_Models_Validator_Abstract $validator
     */
    public function setValidator(ZfExtended_Models_Validator_Abstract $validator) {
      $this->validator = $validator;
    }

    /**
     * @return ZfExtended_Models_Validator_Abstract
     */
    public function getValidator() {
      $this->validatorLazyInstatiation();
      return $this->validator;
    }
    
    /**
     * truncates the given value to the length defined in the DB for the given field
     * @param string $field
     * @param string $value
     * @return string the truncated string
     */
    public function truncateLength($field, $value) {
        if(!is_string($field)) {
            return $value;
        }
        $db = $this->db;
        $md = $db->info($db::METADATA);
        if(empty($md[$field]) || empty($md[$field]['LENGTH'])) {
            return $value;
        }
        return (string)mb_substr($value, 0, $md[$field]['LENGTH'], 'utf-8');
    }
    
    /***
     * Get specificData field value. The returned value will be json decoded.
     * If $propertyName is provided, only the value for this field will be returned if exisit.
     * @param string $propertyName
     * @return mixed|NULL
     */
    public function getSpecificData($propertyName=null){
        $specificData=$this->__call('getSpecificData', array());
        
        if(empty($specificData)){
            return null;
        }
        //try to decode the data
        try {
            $specificData=json_decode($specificData);
            
            //return the property name value if exist
            if(isset($propertyName)){
                return $specificData->$propertyName ?? null;
            }
            return $specificData;
        } catch (Exception $e) {
            
        }
        return null;
    }
    
    /***
     * Set the specificData field. The given value will be json encoded.
     * @param string $value
     */
    public function setSpecificData($value){
        $this->__call('setSpecificData', array(
            json_encode($value)
        ));
    }
    
    /***
     * Add specific data by propert name and value. The result will be encoded back to json
     * @param string $propertyName
     * @param mixed $value
     * @return boolean
     */
    public function addSpecificData($propertyName,$value) {
        $specificData=$this->getSpecificData();
        if(empty($specificData)){
            $this->setSpecificData(array($propertyName=>$value));
            return true;
        }
        //set the property name into the specific data
        $specificData->$propertyName=$value;
        $this->setSpecificData($specificData);
        return true;
    }
    
    /***
     * Get the next autoincrement primary key value for the entity
     * @return mixed
     */
    public function getNextAutoincrement(){
        $query = "SHOW TABLE STATUS LIKE ?;";
        $result = $this->db->getAdapter()->fetchRow($query,[$this->tableName]);
        return $result['Auto_increment'];
    }
    
    public function setDefaultGroupBy(string $defaultGroupBy){
        $this->defaultGroupBy=$defaultGroupBy;
    }
}
