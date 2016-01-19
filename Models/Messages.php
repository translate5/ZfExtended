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
        if(!empty($data['msg'])) {
            $translate = ZfExtended_Zendoverwrites_Translate::getInstance();
            $data['msg'] = $translate->_($data['msg']);
        }
        self::$messages[] = $data;
    }
    
    /**
     * adds a notice msg, origin is core or the pluginname
     * 
     * @param string $msg
     * @param string $origin
     * @param string $id
     */
    public function addNotice($msg, $origin = 'core', $id = null) {
        $this->add(compact('origin', 'msg', 'id'), self::TYPE_NOTICE);
    }
    
    /**
     * adds a error msg, origin is core or the pluginname
     * @param string $msg
     * @param string $origin
     * @param string $id
     */
    public function addError($msg, $origin = 'core', $id = null) {
        $this->add(compact('origin', 'msg', 'id'), self::TYPE_ERROR);
    }
    
    /**
     * adds a warning msg, origin is core or the pluginname
     * @param string $msg
     * @param string $origin
     * @param string $id
     */
    public function addWarning($msg, $origin = 'core', $id = null) {
        $this->add(compact('origin', 'msg', 'id'), self::TYPE_WARNING);
    }
    
    /**
     * returns the internally stored messages as an array
     * @return multitype:
     */
    public function toArray() {
        return self::$messages;
    }
}

