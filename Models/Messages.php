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

