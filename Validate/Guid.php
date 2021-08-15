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

/* 
 * Validiert eine GUID
 */
class ZfExtended_Validate_Guid extends Zend_Validate_Abstract
{
    const GUID = 'GUID';
    protected $_messageTemplates = array(
      self::GUID => "'%value%' ist keine GUID"
    );
    
    /**
     * per default we do not allow empty guids
     * @var boolean
     */
    protected $allowEmpty = false;
    
    /**
     * Sets validator options
     * Accepts the following option keys:
     *   'allowEmpty' => boolean, validates an null value as valid 
     *
     * @param  array|Zend_Config $options
     * @return void
     */
    public function __construct($options = null)
    {
        if(empty($options)) {
            return;
        } elseif ($options instanceof Zend_Config) {
            $options = $options->toArray();
        } elseif (!is_array($options)) {
            $temp = func_get_args();
            if (!empty($temp)) {
                $options = array('allowEmpty' => array_shift($temp));
            }
        }

        if (is_array($options) && array_key_exists('allowEmpty', $options)) {
            $this->setAllowEmpty((bool) $options['allowEmpty']);
        }

    }
    
    /**
     * @param bool $allow
     */
    public function setAllowEmpty($allow) {
        $this->allowEmpty = $allow;
    }
    
    public function isValid($value)
    {
      $this->_setValue($value);
      $config = Zend_Registry::get('config');

      if($this->allowEmpty && empty($value)) {
          return true;
      }
      
      if (!preg_match($config->runtimeOptions->defines->GUID_REGEX, $value)) {
          $this->_error(self::GUID);
          return false;
      }
      return true;
    }
}