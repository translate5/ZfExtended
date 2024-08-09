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

use MittagQI\ZfExtended\Acl\ClientRestriction;

abstract class ZfExtended_Models_Entity_Abstract
{
    /**
     * @var string
     */
    public const VERSION_FIELD = 'entityVersion';

    /**
     * @var Zend_Db_Table_Abstract
     */
    public $db;

    /**
     * maps the Object Attributes (key) to the Row Field Names (value)
     * @var array
     */
    protected $mapping = [];

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
     * This can be used to define, that the entity needs to be filtered for clients because the current user is client-restricted
     * format usually is like ['field' => 'customerId', 'type' => 'list];
     * If just an empty or incomplete array is given, a column-name of "customerId" and a type of "list" is assumed
     * If the filter points to an association, a complete config like ['field' => 'customerIds', 'type' => 'list', 'assoc' => ['table' => 'assocTable', 'foreignKey' => 'entityTableId', 'localKey' => 'id', 'searchField' => 'customerId']] must be given
     * This config reflects the param-naming of a ZfExtended_Models_Filter_JoinAbstract, see the doc there
     */
    protected ?array $clientAccessRestriction = null;

    /**
     * the Validator Instance
     * @var ZfExtended_Models_Validator_Abstract
     */
    protected $validator;

    /**
     * @var integer
     */
    protected $offset = 0;

    protected $limit = 0;

    /**
     * Assoc array of Field Names the set method was called for.
     * To avoid array buildup the field names are the keys
     * @var array
     */
    protected $modified = [];

    /**
     * List of Field Values overwritten by setting a new value
     * @var array
     */
    protected $modifiedValues = [];

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

    protected bool $debugFiltering = false;

    public function __construct()
    {
        $this->db = ZfExtended_Factory::get($this->dbInstanceClass);
        $this->events = ZfExtended_Factory::get(ZfExtended_EventManager::class, [get_class($this)]);
        $this->init();
        $db = $this->db;
        $this->tableName = $db->info($db::NAME);
        $this->debugFiltering = ZfExtended_Debug::hasLevel('core', 'EntityFilter');
    }

    public function hydrate(array|Zend_Db_Table_Row_Abstract $data): void
    {
        $this->row = is_array($data) ? $this->db->createRow($data) : $data;
    }

    /**
     * inits the Entity, resets the internal data
     * if data object is given, use it's values.
     * If $assumeDatabase we "assume" that the given data really already exists in database.
     * @param bool $assumeDatabase
     */
    public function init(array $data = null, $assumeDatabase = false)
    {
        if (empty($data)) {
            $this->row = $this->db->createRow();
        } else {
            $this->row = $this->db->createRow($data);
            if ($assumeDatabase) {
                $this->row->refresh();
            }
        }
    }

    /**
     * Initializes an entity by the related Zend-Db table row
     */
    protected function initByRow(Zend_Db_Table_Row_Abstract $row)
    {
        $this->row = $row;
    }

    /**
     * Deep Cloning of the internal data object
     * else all cloned objects will only have a reference to the same $this->row
     */
    public function __clone()
    {
        $this->row = clone $this->row;
    }

    /**
     * loads the Entity by Primary Key Id
     * @param int $id
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function load($id)
    {
        try {
            $rowset = $this->db->find($id);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
        }
        if (! $rowset || $rowset->count() == 0) {
            $this->notFound('#PK', $id);
        }
        $rowset->rewind();

        //load implies loading one Row, so use only the first row
        return $this->row = $rowset->current();
    }

    /**
     * Refreshes our row
     */
    public function refresh()
    {
        $this->row->refresh();
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
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function loadRow($where = null, $whereValue = null, $whereType = null, $order = null)
    {
        $s = null;
        if (! is_null($where)) {
            $s = $this->db->select();
            $s->where($where, $whereValue, $whereType);
        }
        $this->row = $this->db->fetchRow($s, $order);
        if (empty($this->row)) {
            $this->notFound('#where ' . $where, $whereValue);
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
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function loadRowBySelect(Zend_Db_Table_Select $s)
    {
        $this->row = $this->db->fetchRow($s);
        if (empty($this->row)) {
            //TODO: this will return the sql via api
            //{
            //             "errorCode": null,
            //             "httpStatus": 404,
            //             "errorMessage": "User Entity Not Found: Key: #bySelect; Value: SELECT `Zf_users`.* FROM `Zf_users` WHERE (login = 'xxx@xxx.com') LIMIT 1",
            //             "message": "Not Found",
            //             "success": false
            //             }
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
    protected function notFound($key = '', $value = '')
    {
        throw new ZfExtended_Models_Entity_NotFoundException($this->getDisplayClassName() . ' Entity Not Found: Key: ' . $key . '; Value: ' . $value);
    }

    /**
     * Small helper to retrieve our classname for display in error-msg's etc.
     */
    protected function getDisplayClassName(): string
    {
        // some classes have names like My_Special_Item_Entity and we do not want the message just to be "Entity Entity Not Found"
        $cls = explode('_', get_class($this));

        return (strtolower(end($cls)) === 'entity' && count($cls) > 1) ? $cls[count($cls) - 2] . end($cls) : end($cls);
    }

    /**
     * loads all Entities out of DB
     * @return array
     */
    public function loadAll()
    {
        $s = $this->db->select();

        return $this->loadFilterdCustom($s);
    }

    /**
     * Fetches all filtered entities from the DB
     * Opposed to loadAll this will not return raw data but Entity Objects
     * @return ZfExtended_Models_Entity_Abstract[]
     */
    protected function loadAllEntities(): array
    {
        $entities = [];
        $select = $this->db->select();
        $this->applyFilterAndSort($select);

        return $this->loadSelectedEntities($select);
    }

    /**
     * Creates Entity-Objects from the passed Zend-Select
     * @return ZfExtended_Models_Entity_Abstract[]
     */
    protected function loadSelectedEntities(Zend_Db_Table_Select $select): array
    {
        $entities = [];
        foreach ($this->db->fetchAll($select) as $row) {
            $entity = new static();
            $entity->initByRow($row);
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * @return array
     */
    protected function loadFilterdCustom(Zend_Db_Select $s)
    {
        $this->applyFilterAndSort($s);
        $result = $this->db->fetchAll($s)->toArray();
        // the select can only be traced after being assembled
        if ($this->debugFiltering) {
            error_log(
                "\n----------\n"
                . "FETCH ENTIITY " . get_class($this) . "\n"
                . $s->__toString()
                . "\n==========\n"
            );
        }

        return $result;
    }

    /***
     * apply the filter and sort to the select query
     * @param Zend_Db_Select $s
     */
    protected function applyFilterAndSort(Zend_Db_Select &$s)
    {
        $this->applyFilterToSelect($s);

        if ($this->offset || $this->limit) {
            $s->limit($this->limit, $this->offset);
        }
    }

    /**
     * Applies the configured filters to the given select
     */
    protected function applyFilterToSelect(Zend_Db_Select &$select): void
    {
        // this applies a potential role-dependant client-restriction
        $filter = $this->createClientRestrictedFilter();
        if (! empty($filter)) {
            if ($this->debugFiltering) {
                error_log(
                    "\n----------\n"
                    . "FILTER ENTIITY " . get_class($this) . "\n"
                    . $filter->debug()
                    . "\n==========\n"
                );
            }
            $filter->applyToSelect($select);
        }
    }

    /**
     * Adds a potential client-restriction to the configured filter
     */
    protected function createClientRestrictedFilter(): ?ZfExtended_Models_Filter
    {
        // HINT: This may instantiates the Authentication very early. In case of a ZfExtended_Models_Config entity,
        // this would be too early as it would be called before the session actually is started leading to exceptions
        // Therefore a config-entity currently can not have a client-restriction
        if ($this->clientAccessRestriction !== null && ZfExtended_Authentication::getInstance()->isUserClientRestricted()) {
            $clientIds = ZfExtended_Authentication::getInstance()->getUser()->getRestrictedClientIds();
            $restriction = new ClientRestriction($this->clientAccessRestriction);
            if (empty($this->filter)) {
                $filter = $restriction->create($this, $clientIds);
            } else {
                $filter = clone $this->filter;
                $restriction->apply($filter, $clientIds);
            }

            return $filter;
        }

        return empty($this->filter) ? null : $this->filter;
    }

    /**
     * Checks the client-restriction for the current row
     * If the client-restriction is not fullfilled, a NoAccessException is thrown
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function checkClientRestriction()
    {
        if ($this->clientAccessRestriction !== null && ZfExtended_Authentication::getInstance()->isUserClientRestricted()) {
            $clientIds = ZfExtended_Authentication::getInstance()->getUser()->getRestrictedClientIds();
            $restriction = new ClientRestriction($this->clientAccessRestriction);
            if (! $restriction->isAccessible($this, $clientIds)) {
                throw new ZfExtended_Models_Entity_NoAccessException($this->getDisplayClassName() . ' Entity not accessible due to the users client-restriction');
            }
        }
    }

    /**
     * returns the total (without LIMIT) count of rows
     */
    public function getTotalCount(): int
    {
        $s = $this->db->select();

        return $this->computeTotalCount($s);
    }

    /**
     * computes the total (without LIMIT) count of rows, applies filters to the given Select Statement
     * @return integer
     */
    protected function computeTotalCount(Zend_Db_Select $s): int
    {
        $this->applyFilterToSelect($s);

        $name = $this->db->info(Zend_Db_Table_Abstract::NAME);
        $schema = $this->db->info(Zend_Db_Table_Abstract::SCHEMA);

        $from = $s->getPart($s::FROM);
        if (empty($from[$name]) && ! in_array($name, array_column($from, 'tableName'), true)) {
            $s->from($name, [
                'numrows' => 'count(*)',
            ], $schema);
        } else {
            $s->reset($s::COLUMNS);
            $s->columns([
                'numrows' => 'count(*)',
            ]);
        }
        $totalCount = (int) $this->db->fetchRow($s)->numrows;
        $s->reset($s::COLUMNS);
        $s->reset($s::FROM);

        return $totalCount;
    }

    /**
     * saves the Entity to the DB
     * @return mixed  The primary key value(s), as an associative array if the key is compound, or a scalar if the key is single-column.
     * @throws Zend_Db_Statement_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    public function save()
    {
        // Trigger beforeSave-event
        $this->events->trigger("beforeSave", $this, [
            'entity' => $this,
        ]);

        // Set up $insert flag indicating whether will it be an INSERT of a new record or UPDATE of an existing record
        $insert = ! $this->getId();

        // Setup hooks to be triggered before save attempt
        $hooks = [$insert ? 'onBeforeInsert' : 'onBeforeUpdate', 'onBeforeSave'];

        // Trigger those
        foreach ($hooks as $hook) {
            $this->$hook();
        }

        // Try save
        try {
            // Do save
            $pk = $this->row->save();

            // Setup hooks to be triggered after save attempt
            $hooks = [$insert ? 'onAfterInsert' : 'onAfterUpdate', 'onAfterSave'];

            // Trigger those
            foreach ($hooks as $hook) {
                $this->$hook();
            }

            // Return value, returned by
            return $pk;

            // Catch exception
        } catch (Zend_Db_Statement_Exception $e) {
            $this->handleIntegrityConstraintException($e);
        }
    }

    /**
     * Hook method called before record is inserted
     */
    public function onBeforeInsert()
    {
    }

    /**
     * Hook method called before record is updated
     */
    public function onBeforeUpdate()
    {
    }

    /**
     * Hook method called before record is saved
     */
    public function onBeforeSave()
    {
    }

    /**
     * Hook method called after record is inserted
     */
    public function onAfterInsert()
    {
    }

    /**
     * Hook method called after record is updated
     */
    public function onAfterUpdate()
    {
    }

    /**
     * Hook method called after record is saved
     */
    public function onAfterSave()
    {
    }

    /**
     * löscht das aktuelle Entity
     */
    public function delete()
    {
        try {
            $this->row->delete();
        } catch (Zend_Db_Statement_Exception $e) {
            $this->handleIntegrityConstraintException($e);
        }
    }

    /**
     * Handles DB Exceptions: encapsualates Integrity constraint violation into separate expcetions, all others are thrown directly
     * @return never // TODO: add return type on switch to PHP 8.1
     */
    protected function handleIntegrityConstraintException(Zend_Db_Statement_Exception $e)
    {
        $msg = $e->getMessage();
        if (strpos($msg, 'Integrity constraint violation:') === false) {
            throw $e;
        }
        if (str_contains($msg, '1062 Duplicate entry')) {
            preg_match('/\'' . ($this->tableName ?? '') . '.(.*)\'/', $msg, $matches, PREG_OFFSET_CAPTURE, 0);

            throw new ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey('E1015', [
                'entity' => static::class,
                'data' => $this->getDataObject(),
                'field' => is_array($matches[1] ?? null) ? current($matches[1]) : false,
            ], $e);
        }

        $is1451 = strpos($msg, '1451 Cannot delete or update a parent row: a foreign key constraint fails') !== false;
        $is1452 = strpos($msg, '1452 Cannot add or update a child row: a foreign key constraint fails') !== false;
        if ($is1451 || $is1452) {
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
    public function hasField($field)
    {
        return isset($this->row->$field);
    }

    /**
     * Provides the [get|set][Name] Funktions of the Entity, Name is the name of the data field.
     * @param string $name
     * @throws Zend_Exception
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        if ($name == 'get' || $name == 'set') {
            throw new Zend_Exception('Method ' . $name . ' is trapped by call but it is a protected function. use __call(' . $name . '.ucfirst($field)) instead!');
        }
        $method = substr($name, 0, 3);
        $fieldName = lcfirst(substr($this->_getMappedRowField($name), 3));
        switch ($method) {
            case 'get':
                return $this->get($fieldName);
            case 'set':
                if (! isset($arguments[0])) {
                    $arguments[0] = null;
                }
                $this->modified[$fieldName] = 1;
                if (! array_key_exists($fieldName, $this->modifiedValues)) {
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
    public function setEntityVersion($version)
    {
        if ($this->hasField(self::VERSION_FIELD)) {
            //sets the version to be compared as entitiy value, is evaluated by trigger
            $this->__call(__FUNCTION__, [$version]);
        } else {
            //sets the version to be compared as mysql var, is evaluated by trigger
            $this->db->getAdapter()->query('SET @`' . self::VERSION_FIELD . '` := ' . (int) $version . ';');
        }
    }

    /**
     * sets the value of the given data field
     * @param string $name
     * @param mixed $value
     */
    protected function set($name, $value)
    {
        $field = $this->_getMappedRowField($name);
        $this->row->$field = $value;
    }

    /**
     * returns the value of the given data field
     * @param string $name
     */
    protected function get($name)
    {
        $field = $this->_getMappedRowField($name);

        return $this->row->$field;
    }

    /**
     * maps the requested Object Attribute name to the underlying DB Field Name
     * @param string $attribute
     * @return string
     */
    protected function _getMappedRowField($attribute)
    {
        if (! empty($this->mapping[$attribute])) {
            return $this->mapping[$attribute];
        }

        return $attribute;
    }

    /**
     * Magic PHP Function for String Conversion, returns $this as String
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->getDataObject());
    }

    /**
     * returns $this as data in an stdObject
     */
    public function getDataObject(): stdClass
    {
        $data = $this->row->toArray();
        $mapping = array_flip($this->mapping);
        $result = new stdClass();
        foreach ($data as $field => $value) {
            $field = empty($mapping[$field]) ? $field : $mapping[$field];
            $result->$field = $value;
        }

        return $result;
    }

    /***
     * Convert the current row to array object
     * @return array
     */
    public function toArray()
    {
        return $this->row->toArray();
    }

    public function hasRow(): bool
    {
        return $this->row !== null && empty($this->row->toArray()) === false;
    }

    /**
     * limits the result set of the loadAll Request
     * @param int $offset
     * @param int $limit
     */
    public function limit($offset, $limit)
    {
        $this->offset = $offset;
        $this->limit = $limit;
    }

    /**
     * sets the sort order and filters of the loadAll Result.
     */
    public function filterAndSort(ZfExtended_Models_Filter $filter)
    {
        $this->filter = $filter;
    }

    /**
     * returns the internal configured filter
     * @return ZfExtended_Models_Filter
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * returns true if all internal set data is valid
     * @return boolean
     */
    public function isValid()
    {
        $this->validatorLazyInstatiation();

        return $this->validator->isValid($this->row->toArray());
    }

    /**
     * Throws Exception if data is invalid. Does nothing if all is valid.
     * @throws ZfExtended_ValidateException
     */
    public function validate()
    {
        $this->validatorLazyInstatiation();
        if (! $this->validator->isValid($this->getModifiedData())) {
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
    protected function getModifiedData()
    {
        $data = $this->row->toArray();
        $result = [];
        foreach ($data as $field => $value) {
            if (array_key_exists($field, $this->modified)) {
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
    public function isModified($field = null)
    {
        if (empty($field)) {
            return ! empty($this->modified);
        }

        return array_key_exists($field, $this->modified);
    }

    /**
     * returns the value of an attribute before modified, if not modified return actual value
     */
    public function getOldValue($field)
    {
        if ($this->isModified($field)) {
            return $this->modifiedValues[$field];
        }

        return $this->get($field);
    }

    /**
     * returns the modified values (the old values)
     * @return array
     */
    public function getModifiedValues()
    {
        return $this->modifiedValues;
    }

    protected function validatorLazyInstatiation()
    {
        if (empty($this->validator)) {
            $this->validator = ZfExtended_Factory::get($this->validatorInstanceClass, [$this]);
        }
    }

    /**
     * overwrites the default validator
     */
    public function setValidator(ZfExtended_Models_Validator_Abstract $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @return ZfExtended_Models_Validator_Abstract
     */
    public function getValidator()
    {
        $this->validatorLazyInstatiation();

        return $this->validator;
    }

    /**
     * truncates the given value to the length defined in the DB for the given field
     * @param string $field
     * @param string $value
     * @return string the truncated string
     */
    public function truncateLength($field, $value)
    {
        if (! is_string($field)) {
            return $value;
        }
        $db = $this->db;
        $md = $db->info($db::METADATA);
        if (empty($md[$field]) || empty($md[$field]['LENGTH'])) {
            return $value;
        }

        return (string) mb_substr($value, 0, $md[$field]['LENGTH'], 'utf-8');
    }

    /***
     * Get specificData field value. The returned value will be json decoded.
     * If $propertyName is provided, only the value for this field will be returned if exisit.
     *
     * @param string|null $propertyName
     * @param bool $parseAsArray
     *
     * @return mixed|null
     *
     * @throws Zend_Exception
     */
    public function getSpecificData(?string $propertyName = null, bool $parseAsArray = false): mixed
    {
        $specificData = $this->__call('getSpecificData', []);

        if (empty($specificData)) {
            return null;
        }

        //try to decode the data
        try {
            $specificData = json_decode($specificData, $parseAsArray, flags: JSON_THROW_ON_ERROR);

            //return the property name value if exist
            if ($parseAsArray && isset($specificData[$propertyName])) {
                return $specificData[$propertyName];
            } elseif (isset($propertyName)) {
                return $specificData->$propertyName ?? null;
            }

            return $specificData;
        } catch (Exception $e) {
            // Nothing to do here as null will be returned
        }

        return null;
    }

    /***
     * Set the specificData field. The given value will be json encoded.
     * @param string $value
     */
    public function setSpecificData($value)
    {
        $this->__call('setSpecificData', [
            json_encode($value),
        ]);
    }

    /***
     * Add specific data by propert name and value. The result will be encoded back to json
     * @param string $propertyName
     * @param mixed $value
     * @return boolean
     */
    public function addSpecificData($propertyName, $value)
    {
        $specificData = $this->getSpecificData();
        if (empty($specificData)) {
            $this->setSpecificData([
                $propertyName => $value,
            ]);

            return true;
        }
        //set the property name into the specific data
        $specificData->$propertyName = $value;
        $this->setSpecificData($specificData);

        return true;
    }

    public function setDefaultGroupBy(string $defaultGroupBy)
    {
        $this->defaultGroupBy = $defaultGroupBy;
    }

    /***
     * Load all rows where the row key will be the value from the $fieldName result
     * @param string $fieldName
     * @param bool $keylower  : keys will be lowercase if set to true
     * @return array[]
     */
    public function loadAllKeyCustom(string $fieldName, bool $keylower = false)
    {
        $rows = $this->loadAll();
        $result = [];
        foreach ($rows as $row) {
            $key = $row[$fieldName];
            if ($keylower) {
                $key = strtolower($key);
            }
            $result[$key] = $row;
        }

        return $result;
    }

    /***
     * Load all rows in the table as key value pair
     * @param string $key
     * @param string $value
     * @return array|false
     * @throws Zend_Db_Statement_Exception
     */
    public function loadAllKeyValue(string $key, string $value)
    {
        $s = $this->db->select()
            ->from($this->db, [$key, $value]);

        return $this->db->getAdapter()->query($s)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Can be used to set the underlying Zend_Db row to readonly to prevent misuse
     */
    public function lockRow()
    {
        if (isset($this->row) && $this->row->isConnected()) {
            $this->row->setReadOnly(true);
        }
    }
}
