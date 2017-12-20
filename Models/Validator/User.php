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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */

class ZfExtended_Models_Validator_User extends ZfExtended_Models_Validator_Abstract {
  
  /**
   * Validators for User Entity
   * Validation will be done on calling entity->validate
   */
  protected function defineValidators() {
    $config = Zend_Registry::get('config');
    
    $this->addValidator('id', 'int');
    $this->addValidator('userGuid', 'guid');
    $this->addValidator('firstName', 'stringLength', array('min' => 1, 'max' => 255));
    $this->addValidator('surName', 'stringLength', array('min' => 1, 'max' => 255));
    $this->addValidator('login', 'stringLength', array('min' => 6, 'max' => 255));
    $this->addValidator('gender', 'inArray', array(array('f', 'm')));
    $this->addValidator('locale', 'stringLength', array('min' => 2, 'max' => 3));
    $this->addValidator('roles', 'stringLength', array('min' => 0, 'max' => 255));
    $this->addValidator('parentIds', 'stringLength', array('min' => 0, 'max' => 255));
    //FIXME make a regex here!
    $this->setEmailValidator();
    $this->setPasswdValidator();
    $this->setLanguageValidatod('sourceLanguage');
    $this->setLanguageValidatod('targetLanguage');
  }
  
  protected function setEmailValidator() {
      $me = $this;
      $this->addValidatorCustom('email', function($v) use ($me){
          $me->addMessage('email', 'invalidEmail', 'invalidEmail');
          return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
      });
  }
  
  /***
   * Language validator. Check if the given language(id) exist in the languages table
   * @param string $language
   * @throws Zend_Exception
   */
  protected function setLanguageValidatod($language){
      $languageModel=ZfExtended_Factory::get('editor_Models_Languages');
      /* @var $languageModel editor_Models_Languages */
      $langs=$languageModel->loadAll();
      
      if(empty($langs)){
          throw new Zend_Exception('No languages defined. Please use /docs/003fill-LEK-languages-after-editor-sql or define them otherwhise.');
      }
      
      $langIds=[];
      foreach ($langs as $lang){
          $langIds[]=$lang['id'];
      }
      
      $me = $this;
      $languageValidator = function($value) use($me,$langIds,$language) {
          if(is_null($value) || empty($value)){
              return true;
          }
          $value=substr($value, 1,-1);
          $value=explode(',',$value);
          foreach ($value as $single){
              if(!in_array($single, $langIds)) {
                  $me->addMessage($language, 'invalid'.ucfirst($language), 'invalid'.ucfirst($language));
                  return false;
              }
          }
          return true;
      };
      $this->addValidatorCustom($language, $languageValidator,true);
  }
  
  protected function setPasswdValidator() {
    $string = $this->validatorFactory('stringLength', array('min' => 8, 'max' => 255));
    $me = $this;
    $passwdValidator = function($value) use($string, $me) {
        if(is_null($value))
            return true;
        if(!$string->isValid($value)) {
          $me->addMessage('passwd', 'invalidPasswd', 'invalidPasswd');
          return false;
        }
      return true;
    };
    $this->addValidatorCustom('passwd', $passwdValidator,true);
  }
}