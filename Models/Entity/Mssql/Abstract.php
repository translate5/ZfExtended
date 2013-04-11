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

abstract class ZfExtended_Models_Entity_Mssql_Abstract extends ZfExtended_Models_Entity_Abstract{
    
    /**
     * Provides the [get|set][Name] Funktions of the Entity, Name is the name of the data field.  
     * @param string $name
     * @param array $arguments; Bei Set von Binärdaten Parameter binary = true übergeben
     * @throws Zend_Exception
     * @return mixed
     */
    public function __call($name, array $arguments) {
        $method = substr($name, 0, 3);
        $fieldName = lcfirst(substr($this->_getMappedRowField($name), 3));
        switch ($method) {
            case 'get':
                return $this->get($fieldName);
            case 'set':
                if (!isset($arguments[0])) {
                    $arguments[0] = null;
                }
                $this->modified[] = $fieldName;
                if(isset($arguments['binary']) and $arguments['binary']){
                    return $this->set($fieldName, $arguments[0],$arguments['binary']);
                }
                return $this->set($fieldName, $arguments[0]);
        }
        throw new Zend_Exception('Method ' . $name . ' not defined');
    }

    /**
     * sets the value of the given data field
     * @param string $name
     * @param mixed $value
     * @param boolean $binary
     */
    protected function set($name, $value,$binary = false) {
        $field = $this->_getMappedRowField($name);
        if($binary){
            $this->row->$field = array($value, SQLSRV_PARAM_IN,SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY), SQLSRV_SQLTYPE_VARBINARY('max'));
        }
        else{
            $this->row->$field = $value;
        }
    }
}