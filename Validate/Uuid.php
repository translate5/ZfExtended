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

/*
 * validates a Uuid: 0c57ec6e-3fef-4baf-8775-2305e2d0394e - no curly brackets, not configurable!
 */
class ZfExtended_Validate_Uuid extends ZfExtended_Validate_Guid
{
    const UUID = 'UUID';
    protected $_messageTemplates = array(
        self::UUID => "'%value%' ist keine UUID"
    );
    
    public function isValid($value)
    {
      $this->_setValue($value);

      if($this->allowEmpty && empty($value)) {
          return true;
      }
      
      if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value)) {
          $this->_error(self::UUID);
          return false;
      }
      return true;
    }
}