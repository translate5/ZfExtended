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

abstract class ZfExtended_Models_Validator_Abstract {
  /**
   * @var array of Zend_Validate_Interface
   */
  protected $validators = array();
  protected $customValidators = array();
  protected $dontValidateList = array();

  /**
   * Liste mit Feldnamen bei denen null als gültiger Wert erlaubt ist.
   * @var array
   */
  protected $nullAllowed = array();

  protected $messages = array();

  /**
   * Example for defining Validators:
   * @see editor_Models_Validator_Segment::defineValidators
   */
  abstract protected function defineValidators();

  public function __construct() {
    $this->defineValidators();
  }

  /**
   * validates the given assoc array against the defined Validators
   * @param array $data
   * @return boolean
   */
  public function isValid(array $data) {
    $isValid = true;
    foreach($data as $field => $value) {
      if(in_array($field, $this->dontValidateList)) {
        continue;
      }
      $this->checkUnvalidatedField($field);
      $isValid = $this->validateField($field, $value) && $isValid;
      $isValid = $this->walkCustomValidators($field, $value) && $isValid;
    }
    return $isValid;
  }

  /**
   * returns the list of error messages
   * @return array
   */
  public function getMessages() {
    return $this->messages;
  }

  /**
   * adds an error MEssage to the Message List
   * @param string $field Field to assign the error
   * @param string $messageKey System Name of the error (used as array key)
   * @param string $message translated Message
   */
  public function addMessage($field, $messageKey, $message) {
    settype($this->messages[$field], 'array');
    $this->messages[$field][$messageKey] = $message;
  }

  /**
   * If no Validator is given for field, throw Exception.
   * @param string $field
   * @throws Zend_Exception
   */
  protected function checkUnvalidatedField($field) {
      if(empty($this->validators[$field]) && empty($this->customValidators[$field])){
        throw new Zend_Exception('No Validation for Field '.$field.' defined.');
      }
  }

  /**
   * validate One Field with Zend Validators (or compatible), get the error messages
   * @param string $field
   * @param mixed $value
   * @return boolean
   */
  protected function validateField($field, $value) {
    if(is_null($value) && $this->nullAllowed[$field]){
        return true;
    }
    if(isset($this->validators[$field])){
      $result = $this->validators[$field]->isValid($value);
      if(!$result){
        $this->messages[$field] = $this->validators[$field]->getMessages();
      }
      return $result;
    }
    return true;
  }

  /**
   * walks over all custom Validators and chains results.
   * @param string $field
   * @param mixed $value
   * @return boolean
   */
  protected function walkCustomValidators ($field, $value) {
    $result = true;
    if(empty($this->customValidators[$field])){
      return $result;
    }
    foreach($this->customValidators[$field] as $method) {
      $result = $method($value) && $result;
    }
    return $result;
  }

  /**
   * adds a custom validation Function (Closure). Must return boolean. First Parameter is the Value. Multiple Validators are allowed
   * @param string $fieldname
   * @param Closure $validationFunction
   * @param boolean $allowNull optional allows null as valid value
   */
  public function addValidatorCustom($fieldname, Closure $validationFunction, $allowNull = false){
    settype($this->customValidators[$fieldname], 'array');
    $this->customValidators[$fieldname][] = $validationFunction;
    $this->nullAllowed[$fieldname] = (boolean) $allowNull;
  }

  /**
   * Adds a Validator based on Zend_Validate_Interface
   * @param string $fieldname
   * @param Zend_Validate_Interface $validator
   * @throws Zend_Exception
   */
  public function addValidatorInstance($fieldname, Zend_Validate_Interface $validator){
    if(isset($this->validators[$fieldname])) {
      throw new Zend_Exception('Already a Zend Validator for field '.$fieldname.' added. Use Zend_Validate Chaining instead of adding separate Validators');
    }
    $this->validators[$fieldname] = $validator;
  }

  /**
   * Adds a Validator, internally creates a Zend_Validator based on $type
   * @param string $fieldname
   * @param string $type
   * @param array $parameters optional Construction Parameters
   * @param boolean $allowNull optional allows null as valid value
   * @throws Zend_Exception
   */
  public function addValidator($fieldname, $type, array $parameters = array(), $allowNull = false){
    $this->addValidatorInstance($fieldname, $this->validatorFactory($type, $parameters));
    $this->nullAllowed[$fieldname] = (boolean) $allowNull;
  }

  /**
   * the given fieldname will be ignored by the validator
   * @param string $fieldname
   */
  public function addDontValidateField($fieldname){
    $this->dontValidateList[] = $fieldname;
  }

  /**
   * simple Validator Factory. Parameter "name" is looked up in internal List, or expanded to Zend_Validator_Name
   * @todo improve class searching/autoloading
   * @param string $name
   * @param array $parameters optional Construction Parameters
   * @return Zend_Validate_Interface
   */
  public function validatorFactory($name, array $parameters = array()){
    $internalValidators = array('guid' => 'ZfExtended_Validate_Guid',
        'boolean' => 'ZfExtended_Validate_Boolean',
        'md5' => 'ZfExtended_Validate_Md5'
        );
    if(isset($internalValidators[$name])){
      $class = $internalValidators[$name];
    }
    else {
      $class = 'Zend_Validate_'.ucfirst($name);
    }
    return ZfExtended_Factory::get($class, $parameters);
  }
}