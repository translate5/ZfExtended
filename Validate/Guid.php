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