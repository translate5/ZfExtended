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