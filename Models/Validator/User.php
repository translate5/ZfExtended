<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

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
    $this->setEmailValidator();
    $this->setPasswdValidator();
  }
  
  protected function setEmailValidator() {
      $me = $this;
      $this->addValidatorCustom('email', function($v) use ($me){
          $me->addMessage('email', 'invalidEmail', 'invalidEmail');
          return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
      });
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