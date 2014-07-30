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
/**
 * @method void setId() setId(integer $id)
 * @method void setName() setName(string $name)
 * @method void setConfirmed() setConfirmed(boolean $confirmed)
 * @method void setModule() setModule(string $module)
 * @method void setCategory() setCategory(string $category)
 * @method void setValue() setValue(string $value)
 * @method void setDefault() setDefault(string $default)
 * @method void setDefaults() setDefaults(string $defaults) comma seperated values!
 * @method void setType() setType(string $type)
 * @method void setDescription() setDescription(string $desc)
 * 
 * @method integer getId() getId()
 * @method string getName() getName()
 * @method boolean getConfirmed() getConfirmed()
 * @method string getModule() getModule()
 * @method string getCategory() getCategory()
 * @method string getValue() getValue()
 * @method string getDefault() getDefault()
 * @method string getDefaults() getDefaults()
 * @method string getType() getType()
 * @method string getDescription() getDescription()
 */
class ZfExtended_Models_Config extends ZfExtended_Models_Entity_Abstract {
  protected $dbInstanceClass = 'ZfExtended_Models_Db_Config';
  //protected $validatorInstanceClass = 'ZfExtended_Models_Validator_User';
}
