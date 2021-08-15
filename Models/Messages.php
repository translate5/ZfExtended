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

/**
 * This class is mentioned to transfer error messages / notices / warnings from the
 * PHP Backend to the the GUI by RestController
 * The messages are stored static so that a ZfExtended_Models_Messages instance
 * has not to be piped through all instances
 */
class ZfExtended_Models_Messages {
    const TYPE_ERROR = 'error';
    const TYPE_NOTICE = 'notice';
    const TYPE_WARNING = 'warning';
    
    /**
     * @var array
     */
    static $messages = array();
    
    /**
     * @param array $data
     * @param string $type
     */
    protected function add(array $data, $type) {
        $data['type'] = $type;
        if(empty($data['id'])) {
            //we have to remove the null id here, since ExtJSs markInvalids findField will evaluate
            // this to the first segment with an empty dataIndex (which is nearly every formfield!)
            // same when unsetting it, so the only solution is to init it with a JS non bool value
            // which does not evaluate to any field, this is -1
            $data['id'] = -1;
        }
        if(!empty($data['msg'])) {
            $translate = ZfExtended_Zendoverwrites_Translate::getInstance();
            $data['msg'] = $translate->_($data['msg']);
        }
        self::$messages[] = $data;
    }
    
    /**
     * adds a notice msg, origin is core or the pluginname, data will be added untranslated
     *
     * @param string $msg untranslated string
     * @param string $origin
     * @param string $id
     * @param string $data
     */
    public function addNotice($msg, $origin = 'core', $id = null, $data = null) {
        $this->add(compact('origin', 'msg', 'id', 'data'), self::TYPE_NOTICE);
    }
    
    /**
     * adds a error msg, origin is core or the pluginname, data will be added untranslated
     * @param string $msg untranslated string
     * @param string $origin
     * @param string $id
     * @param string $data
     */
    public function addError($msg, $origin = 'core', $id = null, $data = null) {
        $this->add(compact('origin', 'msg', 'id', 'data'), self::TYPE_ERROR);
    }
    
    /**
     * adds a error msg, origin is core or the pluginname, data will be added untranslated
     * @param ZfExtended_Exception $e
     */
    public function addException(ZfExtended_Exception $e) {
        $msg = $e->getMessage();
        $data = $e->getErrors();
        if(method_exists($e, 'getDomain')) {
            $origin = $e->getDomain();
        }
        else {
            $origin = 'core';
        }
        $id = null;
        $this->add(compact('origin', 'msg', 'id', 'data'), self::TYPE_ERROR);
    }
    
    /**
     * adds a warning msg, origin is core or the pluginname, data will be added untranslated
     * @param string $msg untranslated string
     * @param string $origin
     * @param string $id
     * @param string $data
     */
    public function addWarning($msg, $origin = 'core', $id = null, $data = null) {
        $this->add(compact('origin', 'msg', 'id', 'data'), self::TYPE_WARNING);
    }
    
    /**
     * returns the internally stored messages as an array
     * @return multitype:
     */
    public function toArray() {
        return self::$messages;
    }
}

