<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
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
            $this->setAllowEmpty((boolean) $options['allowEmpty']);
        }

    }
    
    /**
     * @param boolean $allow
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