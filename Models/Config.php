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
 * 
 * The conversion from DB Storage Format to Zend Config Format is done by ZfExtended_Resource_DbConfig
 */
class ZfExtended_Models_Config extends ZfExtended_Models_Entity_Abstract {
  protected $dbInstanceClass = 'ZfExtended_Models_Db_Config';
  //protected $validatorInstanceClass = 'ZfExtended_Models_Validator_User';
}
