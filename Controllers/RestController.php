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

abstract class ZfExtended_RestController extends Zend_Rest_Controller {

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
  protected $filterClass = 'ZfExtended_Models_Filter_ExtJs';

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
   * @var array
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
   * inits the internal entity Object, handels given limit, filter and sort parameters
   * @see Zend_Controller_Action::init()
   */
  public function init() {
      $this->entity = ZfExtended_Factory::get($this->entityClass);
      $this->_helper->viewRenderer->setNoRender(true);
      $this->_helper->layout->disableLayout();
      $this->handleLimit();
      $this->handleFilterAndSort();
  }

  /**
   * 
   */
  public function processClientReferenceVersion() {
      $entity = $this->entity;

      //if the entity self has a version field, we rely on this and must not use headers etc.
      if($entity->hasField($entity::VERSION_FIELD)) {
          return;
      }
      
      $version = $this->_request->getHeader(self::ENTITY_VERSION_HEADER);
      if($version === false) {
          $data = get_object_vars($this->data);
          if(! isset($data[$entity::VERSION_FIELD])) {
              return; //no version is set either in header nor in given data
          }
          $version = $data[$entity::VERSION_FIELD];
      }
      $entity->setEntityVersion($version);
  }

  /**
   * handles given limit parameters and applies them to the entity
   */
  protected function handleLimit() {
    $offset = $this->_getParam('start');
    $limit = $this->_getParam('limit');
    settype($offset, 'integer');
    settype($limit, 'integer');
    $this->entity->limit(max(0, $offset), $limit);
  }

  /**
   * handles given filter and sort parameters and applies them to the entity
   * actual using fixed ExtJS formatted Parameters
   */
  protected function handleFilterAndSort() {
    //Der RestController entscheidet anhand der Konfiguration und/oder
    //der Client Anfrage in welchem Format die Filter und Sortierungsinfos kommen.
    //Aktuell gibt es nur das ExtJS Format, sollte sich das je ändern, muss die Instanzieriungs Logik an dieser Stelle geändert
    //oder als "FilterFactory" in der abstrakten ZfExtended_Models_Filter Klasse implementiert werden
    $filter = ZfExtended_Factory::get($this->filterClass,array(
      $this->entity,
      $this->_getParam('filter')
    ));
    
    /* @var $filter ZfExtended_Models_Filter_ExtJs */
    if($this->_hasParam('defaultFilter')) {
        $filter->setDefaultFilter($this->_getParam('defaultFilter'));
    }
    if($this->_hasParam('sort')) {
        $filter->setSort($this->_getParam('sort'));
    }
    $filter->setMappings($this->_sortColMap, $this->_filterTypeMap);
    $this->entity->filterAndSort($filter);
  }

  /**
   * wraps REST Exception Handling around the called Actions
   *
   * - Exception werden REST-konform im Error-Controller
   *
   * @see Zend_Controller_Action::dispatch()
   */
  public function dispatch($action) {
      //@todo Ausgabe Type anders festlegen, siehe http://www.codeinchaos.com/post/3107629294/restful-services-with-zend-framework-part-1
      // Davon ist auch die __toString Methode von ZfExtended_Models_Entity_Abstract betroffen, welche aktuell zum JSON Export genutzt wird
      // Es muss aber die Möglichkeit gegeben sein, die Ausgabe Möglichkeite zu forcen, da z.B. die Daten bereits als JSON vorliegen
      $this->getResponse()->setHeader('Content-Type', 'application/json');
      $this->view->clearVars();
      try {
          parent::dispatch($action);
      }
      //this is the only usefule place in processing REST request to translate 
      //the entityVersion DB exception to an 409 conflict exception
      catch(Zend_Db_Statement_Exception $e) {
          $m = $e->getMessage();
          if(stripos($m, 'raise_version_conflict does not exist') !== false) {
              throw new ZfExtended_VersionConflictException('', 0, $e);
          }
          throw $e;
      }

      if(empty($this->view->message) && empty($this->view->success)) {
          $this->view->message = "OK";
          $this->view->success = true;
          $this->getResponse()->setHttpResponseCode(200);
      }
  }

  /**
   * Validates the entity, exposes possible failures in a common (extjs known) error format
   * returns false if entity is not valid, true otherwise
   * @return boolean
   */
  protected function validate() {
      try {
          $this->entity->validate();
          $this->additionalValidations();
          return true;
      }
      catch (ZfExtended_ValidateException $e) {
          $this->handleValidateException($e);
      }
      return false;
  }

  /**
   * handles a ZfExtended_ValidateException
   * @param ZfExtended_ValidateException $e
   */
  protected function handleValidateException(ZfExtended_ValidateException $e) {
      $this->view->errors = $this->transformErrors($e->getErrors());
      $this->view->message = "NOT OK";
      $this->view->success = false;
      $this->getResponse()->setHttpResponseCode($e->getCode());
  }
  
  /**
   * Empty function mentionend to be overwritten
   * Method is called by this->validate after entity->validate
   */
  protected function additionalValidations() {}
  
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
  protected function transformErrors(array $zendErrors) {
      $result = array();
      foreach($zendErrors as $id => $oneField) {
          if(!is_array($oneField)) {
              $oneField = array($oneField);
          }
          foreach($oneField as $oneMsg) {
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
      $this->view->rows = $this->entity->load($this->_getParam('id'));
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
      if($this->validate()){
          $this->entity->save();
          $this->view->rows = $this->entity->getDataObject();
      }
  }

  public function putAction()
  {
    $this->entity->load($this->_getParam('id'));
    //@todo implement input check, here or in Entity??? => in Entity throws Ecxeption => HTTP 400
    $this->decodePutData();
    $this->processClientReferenceVersion();
    $this->setDataInEntity();
    if($this->validate()){
        $this->entity->save();
        $this->view->rows = $this->entity->getDataObject();
    }
  }

  protected function decodePutData() {
    $this->data = json_decode($this->_getParam('data'));
  }

  /**
   * sets the entity data out of given post / put data.
   * - setzt für in _sortColMap gesetzten Spalten den übergebenen Wert für beide Spalten
   * @param array $reject list of fieldnames to ignore
   * @param boolean $mode defines if given fields are a black (false) or a whitelist (true)
   */
  protected function setDataInEntity(array $fields = null, $mode = self::SET_DATA_BLACKLIST) {
    settype($fields, 'array');
    foreach($this->data as $key => $value) {
        $hasField = in_array($key, $fields);
        $modeWl = $mode === self::SET_DATA_WHITELIST;
        $whiteListed = !$modeWl || $hasField && $modeWl;
        $blackListed = $hasField && $mode === self::SET_DATA_BLACKLIST;
        if($this->entity->hasField($key) && $whiteListed && !$blackListed){
            $this->entity->__call('set'.ucfirst($key), array($value));
            if(isset($this->_sortColMap[$key])){
                $toSort = $this->_sortColMap[$key];
                $value = $this->entity->truncateLength($toSort, $value);
                $this->entity->__call('set'.ucfirst($toSort), array($value));
            }
        }
    }
  }

  public function deleteAction()
  {
    $this->entity->load($this->_getParam('id'));
    $this->processClientReferenceVersion();
    $this->entity->delete();
  }
  /**
   * not implemented so far, therefore BadMethodCallException
   * must be present in Zend_Framework 1.12, therefore solved this way
   */
  public function headAction() {
       throw new ZfExtended_BadMethodCallException(__CLASS__.'->head');
  }
  /**
   * not implemented so far, therefore BadMethodCallException
   * must be present in Zend_Framework 1.12, therefore solved this way
   */
  public function optionsAction() {
       throw new ZfExtended_BadMethodCallException(__CLASS__.'->head');
  }
}
