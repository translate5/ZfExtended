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

class ZfExtended_SessionController extends ZfExtended_RestController {

    /**
     * inits the internal entity Object, handels given limit, filter and sort parameters
     * @see Zend_Controller_Action::init()
     */
    public function init() {
        $this->initRestControllerSpecific();
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::indexAction()
     */
    public function indexAction () {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->index');
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::putAction()
     */
    public function putAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->put');
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::postAction()
     */
    public function postAction() {
        //$this->_userModel = ZfExtended_Factory::get('ZfExtended_Models_User');
        $this->_helper->auth->isValid($login,$passwd);
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::deleteAction()
     */
    public function deleteAction() {
        $this->_getParam('id');
        //DELETE session and ID given in above id
    }
    
    /**
     * decodes the put data and filters them to values the logged in user is allowed to change on himself
     */
    protected function filterDataForAuthenticated() {
        $allowed = array('passwd');
        $this->decodePutData();
        $data = get_object_vars($this->data);
        $keys = array_keys($data);
        $this->data = new stdClass();
        foreach($allowed as $allow) {
            if(in_array($allow, $keys)){
                $this->data->$allow = $data[$allow];
            }
        }
    }
    
    /**
     * remove password hashes from output
     */
    protected function credentialCleanup() {
        if(is_object($this->view->rows) && property_exists($this->view->rows, 'passwd')) {
            unset($this->view->rows->passwd);
        }
        if(is_array($this->view->rows) && isset($this->view->rows['passwd'])) {
            unset($this->view->rows['passwd']);
        }
    }
    
    /**
     * overridden to prepare data on user creation
     * (non-PHPdoc)
     * @see ZfExtended_RestController::decodePutData()
     */
    protected function decodePutData() {
        if($this->alreadyDecoded) {
            return;
        }
        $this->alreadyDecoded = true;
        $this->_request->isPost() || $this->checkIsEditable(); //checkEditable only if not POST
        parent::decodePutData();
        if($this->_request->isPost()) {
            unset($this->data->id);
            $this->data->userGuid = $this->_helper->guid->create(true);
        }
    }

    /**
     * overridden to save the user password not unencrypted and to reset passwd if requested
     * (non-PHPdoc)
     * @see ZfExtended_RestController::setDataInEntity()
     */
    protected function setDataInEntity(array $fields = null, $mode = self::SET_DATA_BLACKLIST){
        parent::setDataInEntity($fields, $mode);
        if(isset($this->data->passwd)) {
            if($this->data->passwd===''||  is_null($this->data->passwd)) {//convention for passwd being reset; 
                $this->data->passwd = null;
            }
            $this->entity->setNewPasswd($this->data->passwd,false);
        }
    }
}