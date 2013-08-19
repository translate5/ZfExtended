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
    $this->addValidator('gender', 'stringLength', array('min' => 1, 'max' => 1));
    $this->addValidator('email', 'emailAddress');
    $this->addValidator('roles', 'stringLength', array('min' => 0, 'max' => 255));
    $this->addValidator('passwd', 'stringLength', array('min' => 8, 'max' => 255));
    $this->addValidator('passwdReset', 'boolean');
  }
}