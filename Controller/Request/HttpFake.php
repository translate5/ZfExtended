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

/**
 * for calling controller actions without making a real api request this class is needed 
 * Example: 
 *         $request = new ZfExtended_Controller_Request('post', 'taskuserassoc', 'editor', ['data' => json_encode($assocData)]);
        require_once 'Controllers/TaskuserassocController.php';
        $controller = new Editor_TaskuserassocController($request, new Zend_Controller_Response_Http());
        $controller->postAction();
 */
class ZfExtended_Controller_Request_HttpFake extends Zend_Controller_Request_Simple {
    /**
     * Faking a get header request, always return false
     * @param string $name
     * @return boolean
     */
    public function getHeader($name) {
        return false;
    }
    
    /**
     * Fake isPost method, answer depends on set action
     * @return boolean
     */
    public function isPost() {
        return $this->getActionName() == 'post';
    }
    
    /**
     * Fake isPut method, answer depends on set action
     * @return boolean
     */
    public function isPut() {
        return $this->getActionName() == 'put';
    }
}