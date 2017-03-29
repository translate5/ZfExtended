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