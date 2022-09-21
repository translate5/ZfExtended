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

abstract class ZfExtended_RestController extends Zend_Rest_Controller
{
    use ZfExtended_Controllers_MaintenanceTrait;

    const SET_DATA_WHITELIST = true;
    const SET_DATA_BLACKLIST = false;
    const ENTITY_VERSION_HEADER = 'Mqi-Entity-Version';

    /**
     * Class Name of the Entity Model
     * @var string
     */
    protected $entityClass;

    /**
     * Default Filter Class to use
     * @var string
     */
    protected $filterClass = 'ZfExtended_Models_Filter_ExtJs6';

    /**
     * Instance of the Entity
     * @var ZfExtended_Models_Entity_Abstract
     */
    protected $entity;

    /**
     * @var mixed
     */
    protected $response;

    /**
     * @var array - request parameters and reults of request processing
     */
    protected $data = array();
    /**
     * maps cols which should be sorted to other cols in the table,
     * which then are used for the sorting process
     * (key = given col, value = col to be used for sorting
     *
     * Mainly for text columns, where a short version is used for sorting,
     * was introduced for MSSQL which cant sort textblobs
     * @var array
     */
    protected $_sortColMap = array();
    /**
     * mappt einen eingehenden Filtertyp auf einen anderen Filtertyp für ein bestimmtes
     * Feld.
     * @var array array($field => array(origType => newType))
     */
    protected $_filterTypeMap = array();

    /**
     * POST Blacklist
     * Blacklisted fields for POST Requests (to ignore autoincrement values)
     */
    protected $postBlacklist = array();

    /**
     * @var ZfExtended_EventManager
     */
    protected $events = false;

    /**
     * @var ZfExtended_Models_Messages
     */
    protected $restMessages;

    /**
     * @var ZfExtended_Logger
     */
    protected $log = false;


    /**
     * @var ZfExtended_Acl
     */
    protected $acl;

    /**
     * stores the last result of validate method
     * @var boolean
     */
    protected $wasValid = false;

    /**
     * Entity list offset as requested from client
     * @var integer
     */
    protected $offset = 0;

    /***
     * Should the data post/put param be decoded to associative array
     * @var bool
     */
    protected bool $decodePutAssociative = false;

    /**
     * View object
     * @var ZfExtended_View
     * @see Zend_Controller_Action::$view
     */
    public $view;

    /**
     * inits the internal entity Object, handels given limit, filter and sort parameters
     * @see Zend_Controller_Action::init()
     */
    public function init()
    {
        $this->entity = ZfExtended_Factory::get($this->entityClass);
        $this->acl = ZfExtended_Acl::getInstance();
        $this->initRestControllerSpecific();
    }

    /**
     * inits all generally needed stuff for restcontrollers beside entity handling
     */
    protected function initRestControllerSpecific()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
        $this->events = ZfExtended_Factory::get('ZfExtended_EventManager', array(get_class($this)));
        $this->restMessages = ZfExtended_Factory::get('ZfExtended_Models_Messages');
        Zend_Registry::set('rest_messages', $this->restMessages);

        $this->log = Zend_Registry::get('logger');

        //perhaps not working under windows, see comment on php.net
        //enable simple front end interaction with fatal errors
        register_shutdown_function(function () {
            $error = error_get_last();
            if (!is_null($error) && ($error['type'] & FATAL_ERRORS_TO_HANDLE)) {
                ob_get_clean(); //to remove Internal Server Error headline
                $res = new stdClass();
                $res->errors = $error;
                echo json_encode($res);
            }
        });
    }

    /**
     * inits sorting and filtering
     * handels maintenance
     * triggers event "before<Controllername>Action"
     */
    public function preDispatch()
    {
        $this->_response->setHeader('x-translate5-version', APPLICATION_VERSION);
        $this->handleLimit();
        $this->prepareFilterAndSort();
        $this->displayMaintenance();
        $this->beforeActionEvent($this->_request->getActionName());
    }

    /**
     * triggers event "after<Controllername>Action"
     */
    public function postDispatch()
    {
        $this->afterActionEvent($this->_request->getActionName());

        //add rest Messages to the error field
        $messages = $this->restMessages->toArray();
        if (empty($messages)) {
            return;
        }
        if (empty($this->view->errors)) {
            $this->view->errors = $messages;
        } else {
            $this->view->errors = array_merge($this->view->errors, $messages);
        }
    }

    /***
     * Trigger before action event for given controller action name
     *
     * @param string $actionName
     */
    public function beforeActionEvent($actionName)
    {
        $eventName = "before" . ucfirst($actionName) . "Action";
        $this->events->trigger($eventName, $this, [
            'entity' => $this->entity,
            'params' => $this->getAllParams(),
            'controller' => $this
        ]);
    }

    /***
     * Trigger after action event for given controller action name
     * @param string $actionName
     */
    public function afterActionEvent($actionName)
    {
        $eventName = "after" . ucfirst($actionName) . "Action";
        $this->events->trigger($eventName, $this, [
            'entity' => $this->entity,
            'view' => $this->view,
            'data' => $this->data,
            'request' => $this->getRequest(),
            'controller' => $this,
        ]);
    }

    /**
     *
     */
    public function processClientReferenceVersion()
    {
        $entity = $this->entity;

        //if the entity self has a version field, we rely on this and must not use headers etc.
        if ($entity->hasField($entity::VERSION_FIELD)) {
            return;
        }

        $version = $this->_request->getHeader(self::ENTITY_VERSION_HEADER);
        if ($version === false) {
            $data = get_object_vars((object)$this->data);
            if (!isset($data[$entity::VERSION_FIELD])) {
                return; //no version is set either in header nor in given data
            }
            $version = $data[$entity::VERSION_FIELD];
        }
        $entity->setEntityVersion($version);
    }

    /**
     * handles given limit parameters and applies them to the entity
     */
    protected function handleLimit()
    {
        if (empty($this->entity) || !$this->entity instanceof ZfExtended_Models_Entity_Abstract) {
            //instance without entity
            return;
        }
        $offset = $this->offset = $this->_getParam('start');
        $limit = $this->_getParam('limit');
        settype($offset, 'integer');
        settype($limit, 'integer');
        $this->entity->limit(max(0, $offset), $limit);
    }

    /**
     * handles given filter and sort parameters and applies them to the entity
     * actual using fixed ExtJS formatted Parameters
     */
    protected function prepareFilterAndSort()
    {
        if (empty($this->entity) || !$this->entity instanceof ZfExtended_Models_Entity_Abstract) {
            //instance without entity
            return;
        }
        //Der RestController entscheidet anhand der Konfiguration und/oder
        //der Client Anfrage in welchem Format die Filter und Sortierungsinfos kommen.
        //Aktuell gibt es nur das ExtJS Format, sollte sich das je ändern, muss die Instanzieriungs Logik an dieser Stelle geändert
        //oder als "FilterFactory" in der abstrakten ZfExtended_Models_Filter Klasse implementiert werden
        $filter = ZfExtended_Factory::get($this->filterClass, array(
            $this->entity,
            $this->_getParam('filter')
        ));

        /* @var $filter ZfExtended_Models_Filter_ExtJs */
        if ($this->hasParam('defaultFilter')) {
            $filter->setDefaultFilter($this->_getParam('defaultFilter'));
        }
        if ($this->hasParam('sort')) {
            $filter->setSort($this->_getParam('sort'));
        }
        $filter->setMappings($this->_sortColMap, $this->_filterTypeMap);
        $this->entity->filterAndSort($filter);
    }

    /**
     * wraps REST Exception Handling around the called Actions
     *
     * Warning: Only Exceptions thrown in the dispatch process are handled correctly in REST calls.
     * Exceptions thrown before are not handled correctly, they are exposed as plain HTML exceptions!
     *
     * @see Zend_Controller_Action::dispatch()
     */
    public function dispatch($action)
    {
        //@todo Ausgabe Type anders festlegen, siehe http://www.codeinchaos.com/post/3107629294/restful-services-with-zend-framework-part-1
        // Davon ist auch die __toString Methode von ZfExtended_Models_Entity_Abstract betroffen, welche aktuell zum JSON Export genutzt wird
        // Es muss aber die Möglichkeit gegeben sein, die Ausgabe Möglichkeite zu forcen, da z.B. die Daten bereits als JSON vorliegen
        $this->getResponse()->setHeader('Content-Type', 'application/json', TRUE);
        $this->view->clearVars();
        try {
            parent::dispatch($action);
        }
            //this is the only useful place in processing REST request to translate
            //the entityVersion DB exception to an 409 conflict exception
        catch (Zend_Db_Statement_Exception $e) {
            $m = $e->getMessage();
            if (stripos($m, 'raise_version_conflict does not exist') !== false) {
                throw new ZfExtended_VersionConflictException('', 0, $e);
            }
            throw $e;
        } catch (ZfExtended_BadGateway $e) {
            $this->handleException($e);
            return;
        }

        if (empty($this->view->message) && empty($this->view->success)) {
            $this->view->message = "OK";
            $this->view->success = true;
            $this->getResponse()->setHttpResponseCode(200);
        }
    }

    /**
     * Validates the entity, exposes possible failures in a common (extjs known) error format
     * returns false if entity is not valid, true otherwise
     * sets also the internal wasValid variable
     * @return boolean
     */
    protected function validate()
    {
        try {
            $this->entity->validate();
            $this->additionalValidations();
            //new event here to invoke to the controller validation call
            $this->events->trigger('onValidate', $this, array('entity' => $this->entity));
            return $this->wasValid = true;
        } catch (ZfExtended_ValidateException $e) {
            $this->handleValidateException($e);
        }
        return $this->wasValid = false;
    }

    /**
     * handles a ZfExtended_ValidateException
     * @param ZfExtended_ValidateException $e
     * @deprecated should be obsolete if new error loggin refactoring is done
     */
    protected function handleValidateException(ZfExtended_ValidateException $e)
    {
        $this->view->errors = $this->transformErrors($e->getErrors());
        $this->handleErrorResponse($e->getCode());
    }

    /**
     * handles a general ZfExtended_Exception
     * @param ZfExtended_Exception $e
     * @deprecated should be obsolete if new error loggin refactoring is done
     */
    protected function handleException(ZfExtended_Exception $e)
    {
        $this->log->exception($e);
        $this->restMessages->addException($e);
        $this->handleErrorResponse($e->getCode());
        //this postDispatch and notifyPostDispatch calls are needed to finish
        // the request properly and render the error messages properly
        $this->postDispatch();
        $this->_helper->notifyPostDispatch();
    }

    /**
     * prepares the result in case of an error
     * @param int $httpStatus
     * @deprecated should be obsolete if new error loggin refactoring is done
     */
    protected function handleErrorResponse($httpStatus)
    {
        $this->view->message = "NOT OK";
        $this->view->success = false;

        //ExtJS does not parse the HTTP Status well on file uploads.
        // In this case we deliver the status as additional information
        if (!empty($_FILES)) {
            $this->view->httpStatus = $httpStatus;
        }

        $this->getResponse()->setHttpResponseCode($httpStatus);
    }

    /**
     * Empty function mentionend to be overwritten
     * Method is called by this->validate after entity->validate
     */
    protected function additionalValidations()
    {
    }

    /**
     * Transforms the Errors in Form of
     * Array (
     *   [affectedFieldName] = Array (
     *     [errorName] => 'Already translated Error String'
     *   )
     * )
     *
     * to a format used by ExtJS:
     *
     * Array (
     *   Object (
     *     [id] => 'affectedFieldName'
     *     [msg] => 'Already translated Error String'
     *   )
     * )
     *
     * @param array $zendErrors
     * @return array
     */
    protected function transformErrors(array $zendErrors)
    {
        $result = array();
        foreach ($zendErrors as $id => $oneField) {
            if (!is_array($oneField)) {
                $oneField = array($oneField);
            }
            foreach ($oneField as $oneMsg) {
                $error = new stdClass();
                $error->id = $id;
                $error->msg = $oneMsg;
                $result[] = $error;
            }
        }
        return $result;
    }

    public function indexAction()
    {
        $this->view->rows = $this->entity->loadAll();
        $this->view->total = $this->entity->getTotalCount();
    }

    public function getAction()
    {
        $this->entityLoad();
        $this->view->rows = $this->entity->getDataObject();
    }

    /**
     * (non-PHPdoc)
     * @see Zend_Rest_Controller::postAction()
     */
    public function postAction()
    {
        $this->entity->init();
        $this->decodePutData();
        $this->processClientReferenceVersion();
        $this->setDataInEntity($this->postBlacklist);
        if ($this->validate()) {
            $this->entity->save();
            $this->view->rows = $this->entity->getDataObject();
        }
    }

    public function putAction()
    {
        $this->entityLoad();
        //@todo implement input check, here or in Entity??? => in Entity throws Ecxeption => HTTP 400
        $this->decodePutData();
        $this->processClientReferenceVersion();
        $this->setDataInEntity();
        if ($this->validate()) {
            $this->entity->save();
            $this->view->rows = $this->entity->getDataObject();
        }
    }

    /***
     * @return void
     */
    protected function decodePutData()
    {
        $this->data = json_decode($this->_getParam('data'), $this->decodePutAssociative);
    }

    /**
     * encapsulating the entity load for simpler overwritting purposes
     */
    protected function entityLoad()
    {
        $this->entity->load($this->_getParam('id'));
    }

    /**
     * sets the entity data out of given post / put data.
     * - setzt für in _sortColMap gesetzten Spalten den übergebenen Wert für beide Spalten
     * @param array $reject list of fieldnames to ignore
     * @param bool $mode defines if given fields are a black (false) or a whitelist (true)
     */
    protected function setDataInEntity(array $fields = null, $mode = self::SET_DATA_BLACKLIST)
    {
        settype($fields, 'array');
        $this->events->trigger('beforeSetDataInEntity', $this, array('entity' => $this->entity, 'data' => $this->data));
        foreach ($this->data as $key => $value) {
            $hasField = in_array($key, $fields);
            $modeWl = $mode === self::SET_DATA_WHITELIST;
            $whiteListed = !$modeWl || $hasField && $modeWl;
            $blackListed = $hasField && $mode === self::SET_DATA_BLACKLIST;
            if ($this->entity->hasField($key) && $whiteListed && !$blackListed) {
                $this->entity->__call('set' . ucfirst($key), array($value));
                if (isset($this->_sortColMap[$key]) && is_string($this->_sortColMap[$key])) {
                    $toSort = $this->_sortColMap[$key];
                    $value = $this->entity->truncateLength($toSort, $value);
                    $this->entity->__call('set' . ucfirst($toSort), array($value));
                }
            }
        }
        $this->events->trigger('afterSetDataInEntity', $this, array('entity' => $this->entity));
    }

    /**
     * checks if authenticated user is allowed to use the resource / privelege
     *
     * @param Zend_Acl_Resource_Interface|string $resource
     * @param string $privilege
     * @return boolean
     */
    protected function isAllowed($resource = null, $privilege = null)
    {
        return $this->acl->isInAllowedRoles(ZfExtended_Authentication::getInstance()->getRoles(), $resource, $privilege);
    }

    /***
     * Join array elements with a comma.
     * The column string will start and end with comma to.
     *
     * @param string $language
     */
    protected function arrayToCommaSeparated($columnName)
    {
        if (isset($this->data->$columnName) && is_array($this->data->$columnName)) {
            $this->data->$columnName = implode(',', $this->data->$columnName);
            if (empty($this->data->$columnName)) {
                $this->data->$columnName = null;
            } else {
                $this->data->$columnName = ',' . $this->data->$columnName . ',';
            }
        }
    }

    public function deleteAction()
    {
        $this->entityLoad();
        $this->processClientReferenceVersion();
        $this->entity->delete();
    }

    /**
     * not implemented so far, therefore BadMethodCallException
     * must be present in Zend_Framework 1.12, therefore solved this way
     */
    public function headAction()
    {
        $e = new ZfExtended_BadMethodCallException(__CLASS__ . '->head not implemented yet');
        $e->setLogging(false); //in future ZfExtended_Log::LEVEL_INFO
        throw $e;
    }

    /**
     * not implemented so far, therefore BadMethodCallException
     * must be present in Zend_Framework 1.12, therefore solved this way
     */
    public function optionsAction()
    {
        $e = new ZfExtended_BadMethodCallException(__CLASS__ . '->options not implemented yet');
        $e->setLogging(false); //in future ZfExtended_Log::LEVEL_INFO
        throw $e;
    }

    /**
     * Batch operations extend the REST world about function calls on a given REST entity index endpoint.
     * Therefore a fooBatch function must exist in the controller or exist as event binding later on.
     */
    public function batchAction()
    {
        $this->dispatchOperation('Batch');
    }

    /**
     * Operations extend the REST world about function calls on a given REST entity.
     * Therefore a fooOperation function must exist in the controller or exist as event binding later on.
     */
    public function operationAction(){
        $this->getAction();
        $this->dispatchOperation('Operation');
    }

    /**
     * @throws Zend_Exception
     * @throws ZfExtended_NoAccessException
     * @throws ZfExtended_NotFoundException
     */
    private function dispatchOperation(string $type)
    {
        $action = $this->getParam('operation') . $type;
        $hasPlainMethod = method_exists($this, $action);

        $module = Zend_Registry::get('module');
        $controller = $this->_request->getControllerName();
        if ($module !== 'default') {
            $controller = $module . '_' . $controller;
        }

        $this->checkAccess($controller, $action, $type);

        if ($hasPlainMethod) {
            $this->$action();
        }
        $response = $this->events->trigger($action, $this, [
            'entity' => $this->entity,
            'params' => $this->getAllParams(),
            'controller' => $this
        ]);
        if ($response->isEmpty() && !$hasPlainMethod) {
            unset($this->view->rows);
            throw new ZfExtended_NotFoundException($type.' not supported');
        }
    }

    /**
     * Checks ACL access to given resource and right and throws no access if needed
     * @param string $resource
     * @param string $right
     * @param string $resourceName
     * @throws ZfExtended_NoAccessException
     */
    protected function checkAccess(string $resource, string $right, string $resourceName): void
    {
        if (!$this->isAllowed($resource, $right)) {
            throw new ZfExtended_NoAccessException('Access to ' . $resourceName . ' not permitted');
        }
    }
}
