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

class ZfExtended_UserController extends ZfExtended_RestController {

    protected $entityClass = 'ZfExtended_Models_User';

    /**
     * @var ZfExtended_Models_User
     */
    protected $entity;
    
    /**
     * flag to preserve twice put data encoding
     * @var boolean
     */
    protected $alreadyDecoded = false;
    
    public function init() {
        //add filter type for languages
        $this->_filterTypeMap = array(
                array('sourceLanguage' => array('list' => 'listCommaSeparated')),
                array('targetLanguage' => array('list' => 'listCommaSeparated'))
        );
        parent::init();
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::indexAction()
     * 
     * FIXME Sicherstellen, dass für nicht PMs diese Methode nur die User liefert, die gemeinsam mit dem aktuellen User an Tasks arbeiten.
     * FIXME Generell werden nur User mit der Rolle "editor" angezeigt, alle anderen haben eh keinen Zugriff auf T5
     */
    public function indexAction() {
        $isAllowed=$this->isAllowed("backend","seeAllUsers");
        if($isAllowed){
            $rows= $this->entity->loadAll();
            $count= $this->entity->getTotalCount();
        }else{
            $rows= $this->entity->loadAllOfHierarchy();
            $count= $this->entity->getTotalCountHierarchy();
        }
        $this->view->rows=$rows;
        $this->view->total=$count;
        $this->languagesCommaSeparatedToArray();
    }
    
    /**
     * Loads a list of all users with role 'pm'
     */
    public function pmAction()
    {
        //check if the user is allowed to see all users
        if($this->isAllowed("backend","seeAllUsers")){
            $parentId = false;
        }
        else {
            $parentId = $userSession->data->id;
        }
        $this->view->rows = $this->entity->loadAllByRole('pm', $parentId);
        $this->view->total = $this->entity->getTotalCount();
        $this->languagesCommaSeparatedToArray();
    }
    
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::putAction()
     */
    public function putAction() {
        try {
            parent::putAction();
            $this->handlePasswdMail();
            $this->credentialCleanup();
            $this->languagesCommaSeparatedToArray();
        }
        catch(Zend_Db_Statement_Exception $e) {
            $this->handleLoginDuplicates($e);
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::postAction()
     */
    public function postAction() {
        try {
            parent::postAction();
            $this->handlePasswdMail();
            $this->credentialCleanup();
            $this->languagesCommaSeparatedToArray();
        }
        catch(Zend_Db_Statement_Exception $e) {
            $this->handleLoginDuplicates($e);
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::getAction()
     */
    public function getAction() {
        parent::getAction();
        $this->handleUserParentId();
        $this->languagesCommaSeparatedToArray();
        if($this->entity->getLogin() == ZfExtended_Models_User::SYSTEM_LOGIN) {
            $e = new ZfExtended_Models_Entity_NotFoundException();
            $e->setMessage("System Benutzer wurde versucht zu erreichen",true);
            throw $e;
        }
        $this->credentialCleanup();
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::deleteAction()
     */
    public function deleteAction() {
        $this->entity->load($this->_getParam('id'));
        $this->checkIsEditable();
        $this->handleUserParentId();
        $this->entity->delete();
    }
    
    /**
     * encapsulate a separate REST sub request for authenticated users only.
     * A authenticated user is allowed to get and change (PUT) himself, nothing more, nothing less.
     * @throws ZfExtended_BadMethodCallException
     */
    public function authenticatedAction() {
        $userSession = new Zend_Session_Namespace('user');
        $id = $userSession->data->id;
        $this->_setParam('id', $id);
        if($this->_request->isPut()){
            $this->entity->load($id);
            $this->filterDataForAuthenticated();
            return $this->putAction();
        }
        if($this->_request->isGet()){
            return $this->getAction();
        }
        throw new ZfExtended_BadMethodCallException();
    }
    
    /***
     * converts the source and target comma separated language ids to array.
     * Frontend/api use array, in the database we save comma separated values.
     */
    protected function languagesCommaSeparatedToArray(){
        $callback=function($row){
            if($row!==null && $row!==""){
                $row=substr($row, 1,-1);
                $row=explode(',', $row);
            }
            return $row;
        };
        //if the row is an array, loop over its elements, and explode the source/target language
        if(is_array($this->view->rows)){
            foreach ($this->view->rows as &$singleRow){
                $singleRow['sourceLanguage']=$callback($singleRow['sourceLanguage']);
                $singleRow['targetLanguage']=$callback($singleRow['targetLanguage']);
            }
            return;
        }
        
        $this->view->rows->sourceLanguage=$callback($this->view->rows->sourceLanguage);
        $this->view->rows->targetLanguage=$callback($this->view->rows->targetLanguage);
    }
    
    /***
     * Convert the language array to comma separated values.
     * Frontend/api use array, in the database we save comma separated values.
     */
    protected function languagesArrayToCommaSeparated(){
        $this->arrayToCommaSeparated('sourceLanguage');
        $this->arrayToCommaSeparated('targetLanguage');
    }
    
    /***
     * If the language array exist in the request data, it will be converted to comma separated value
     * @param string $language
     */
    private function arrayToCommaSeparated($language){
        if(isset($this->data->$language) && is_array($this->data->$language)) {
            $this->data->$language=implode(',', $this->data->$language);
            if(empty($this->data->$language)){
                $this->data->$language=null;
            }else{
                $this->data->$language=','.$this->data->$language.',';
            }
        }
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
        $this->languagesArrayToCommaSeparated();
        if($this->_request->isPost()) {
            unset($this->data->id);
            $this->data->userGuid = $this->_helper->guid->create(true);
        }
        $this->handleUserParentId();
        $this->handleUserSetAclRole();
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
    
    
    /**
     * handles the exception if its an duplication of the login field
     * @param Zend_Db_Statement_Exception $e
     * @throws Zend_Db_Statement_Exception
     */
    protected function handleLoginDuplicates(Zend_Db_Statement_Exception $e) {
        $msg = $e->getMessage();
        if(stripos($msg, 'duplicate entry') === false || stripos($msg, "for key 'login'") === false) {
            throw $e; //otherwise throw this again
        }
        
        $t = ZfExtended_Zendoverwrites_Translate::getInstance();
        /* @var $t ZfExtended_Zendoverwrites_Translate */;;
        
        $errors = array('login' => $t->_('Dieser Anmeldename wird bereits verwendet.'));
        $e = new ZfExtended_ValidateException();
        $e->setErrors($errors);
        $this->handleValidateException($e);
    }

    /**
     * send a mail to user, if passwd has been reseted or account has been new created
     */
    protected function handlePasswdMail() {
        //convention for passwd being reset: 
        if(property_exists($this->data, 'passwd') && is_null($this->data->passwd)) {
            $general = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
                'general'
            );
            $translate = ZfExtended_Zendoverwrites_Translate::getInstance();
            /* @var $translate ZfExtended_Zendoverwrites_Translate */;
            $general->mail(
                    $this->entity->getEmail(),
                    '',
                    $translate->_('Passwort setzen'),
                    array(
                        'userEntity' =>$this->entity
                    )
            );
        }
    }
    
    /**
     * checks if the loaded entity is editable, if not throw an exception
     * we decided to use a normal exception here, not a NotAllowedExeception 
     * since editing a not editable user should not happen from frontend
     * @throws Zend_Exception
     */
    protected function checkIsEditable(){
        if(! $this->entity->getEditable()){
            throw new Zend_Exception('Tryied to manipulate a not editable user'); 
        }
    }
    
    /***
     * Check in get/put/delete actions if the current logged in user is parent of the data(user)
     * which needs to be modified
     * @throws ZfExtended_NoAccessException
     */
    protected function handleUserParentId(){
        $userSession = new Zend_Session_Namespace('user');
        //check if logged in user is able to modefy the data
        if($this->_request->isDelete() || $this->_request->isGet()) {
            $userModel=ZfExtended_Factory::get('ZfExtended_Models_User');
            /* @var $userModel ZfExtended_Models_User */
            
            if(empty($this->entity->getParentIds())){
                return;
            }
            
            $hasParent=$userModel->hasParent($this->entity->getUserGuid(), $userSession->data->id);
            if(!$hasParent && $this->entity->getId()!=$userSession->data->id){
                throw new ZfExtended_NoAccessException();
            }
            
        }else{
            $sameId=false;
            if($this->_request->isPut()) {
                $sameId=$userSession->data->id===$this->data->id;
            }
            //if the same user is modified do not update the parentId
            if($sameId){
                return;
            }
                
            $parentIds=$userSession->data->parentIds;
            if($parentIds){
                $parentIds.=$userSession->data->id.',';
            }else{
                $parentIds=','.$parentIds.$userSession->data->id.',';
            }
            $this->data->parentIds=$parentIds;
        }
        
    }
    
    /***
     * Check if the current user is allowed co set/modefy the post/put selected acl roles
     * 
     * @throws ZfExtended_NoAccessException
     */
    protected function handleUserSetAclRole(){
        $isPost=$this->_request->isPost();
        $isPut=$this->_request->isPut();
        if(!$isPost && !$isPut) {
            return;
        }
            
        if(empty($this->data->roles)){
            return;
        }
        
        $requestAcls=$this->data->roles;
        $requestAclsArray=explode(',',$requestAcls);
        
        if(empty($requestAclsArray)){
            return;
        }
        
        foreach ($requestAclsArray as $role){
            $isAllowed=$this->isAllowed('setaclrole', $role);
            if(!$isAllowed){
                throw new ZfExtended_NoAccessException("Authenticated User is not allowed to modify role ".$role);
            }
        }
    }
}