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
        $this->_filterTypeMap = [
            'customers' => [
                'list' => 'listCommaSeparated',
                'string' => new ZfExtended_Models_Filter_JoinHard('editor_Models_Db_Customer', 'name', 'id', 'customers', 'listCommaSeparated')
            ],
        ];
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
        $this->csvToArray();
    }

    /**
     * Loads a list of all users with role 'pm'. If 'pmRoles' is set, all users with roles listed in 'pmRoles' will be loaded
     */
    public function pmAction()
    {
        //check if the user is allowed to see all users
        if($this->isAllowed("backend","seeAllUsers")){
            $parentId = false;
        }
        else {
            $userSession = new Zend_Session_Namespace('user');
            $parentId = $userSession->data->id;
        }
        $pmRoles = explode(',', $this->getParam('pmRoles', ''));
        $pmRoles[] = 'pm';
        $pmRoles = array_unique(array_filter($pmRoles));
        $this->view->rows = $this->entity->loadAllByRole($pmRoles, $parentId);
        $this->view->total = $this->entity->getTotalByRole($pmRoles, $parentId);
        $this->csvToArray();
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
            if($this->wasValid) {
                $this->csvToArray();
                $this->resetInvalidCounter();
            }
            $this->checkAndUpdateSession();
        }
        catch(ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey $e) {
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
            if($this->wasValid) {
                $this->csvToArray();
            }
        }
        catch(ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey $e) {
            $this->handleLoginDuplicates($e);
        }
    }

    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::getAction()
     */
    public function getAction() {
        parent::getAction();
        $this->checkUserAccessByParent();
        $this->csvToArray();
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
        $this->checkUserAccessByParent();
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
        $this->setParam('id', $id);
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
    protected function csvToArray(){
        $callback=function($row){
            if($row!==null && $row!==""){
                $row=trim($row, ', ');
                $row=explode(',', $row);
            }
            return $row;
        };
        //if the row is an array, loop over its elements, and explode the source/target language
        if(is_array($this->view->rows)){
            foreach ($this->view->rows as &$singleRow){
                $singleRow['parentIds']=$callback($singleRow['parentIds']);
            }
            return;
        }

        $this->view->rows->parentIds = $callback($this->view->rows->parentIds);
    }

    /***
     * After the fields are decoded, modify their values if needed
     */
    protected function convertDecodedFields(){
        //add leading and trailing comma
        if(!empty($this->data->customers)){
            $this->data->customers=','.$this->data->customers.',';
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
     * remove password hashes and openid subject from output
     */
    protected function credentialCleanup() {
        if(is_object($this->view->rows)) {
            if(property_exists($this->view->rows, 'passwd')) {
                unset($this->view->rows->passwd);
            }
            if(property_exists($this->view->rows, 'openIdSubject')) {
                unset($this->view->rows->openIdSubject);
            }
            if(property_exists($this->view->rows, 'openIdIssuer')) {
                unset($this->view->rows->openIdIssuer);
            }
        }
        if(is_array($this->view->rows)) {
            if(isset($this->view->rows['passwd'])) {
                unset($this->view->rows['passwd']);
            }
            if(isset($this->view->rows['openIdSubject'])) {
                unset($this->view->rows['openIdSubject']);
            }
            if(isset($this->view->rows['openIdIssuer'])) {
                unset($this->view->rows['openIdIssuer']);
            }
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
        $this->warnUserLanguages();
        //openId data may not be manipulated via API
        unset($this->data->openIdSubject);
        unset($this->data->openIdIssuer);
        $this->convertDecodedFields();
        if($this->_request->isPost()) {
            unset($this->data->id);
            if(empty($this->data->userGuid)) {
                $this->data->userGuid = ZfExtended_Utils::guid(true);
            }
        }
        $this->handleUserSetAclRole();
    }

    /**
     * overridden to save the user password not unencrypted and to reset passwd if requested
     * (non-PHPdoc)
     * @see ZfExtended_RestController::setDataInEntity()
     */
    protected function setDataInEntity(array $fields = null, $mode = self::SET_DATA_BLACKLIST){
        $this->prepareParentIds();
        parent::setDataInEntity($fields, $mode);
        if(isset($this->data->passwd)) {
            if($this->data->passwd===''||  is_null($this->data->passwd)) {//convention for passwd being reset;
                $this->data->passwd = null;
            }
            $this->entity->setNewPasswd($this->data->passwd,false);
        }
        //if is post add current user as "owner" of the newly created one
        if(!$this->_request->isPost()) {
            //on put we have to check access
            $this->checkUserAccessByParent();
        }
    }

    /**
     * Prepares the parentIds field for the new entity / entity to be edited
     * - From the GUI (via API) may only come a id or a userGuid
     *   This is evaluated to a user, and that users id path is stored then
     * - to change an already set parentIds value the user must have the seeAllUsers flag
     */
    protected function prepareParentIds() {
        if($this->isAllowed("backend", "seeAllUsers") && !empty($this->data->parentIds)) {
            $user = clone $this->entity;
            try {
                if(is_numeric($this->data->parentIds)) {
                    $user->load($this->data->parentIds);
                }
                else {
                    $user->loadByGuid($this->data->parentIds);
                }
            }catch(ZfExtended_Exception $e){
                $e = new ZfExtended_ValidateException();
                $e->setErrors(['parentIds' => 'The given parentIds value can not be evaluated to any user!']);
                $this->handleValidateException($e);
            }
            $userData = $user->getDataObject();
        }
        elseif($this->_request->isPost()) {
            $userSession = new Zend_Session_Namespace('user');
            $userData = $userSession->data;
        }

        //FIXME currently its not possible for seeAllUsers users to remove the parentIds flag by set it to null/""

        if(empty($userData)) {
            if(property_exists($this->data, 'parentIds')){
                unset($this->data->parentIds);
            }
            return;
        }

        if(empty($userData->parentIds)){
            $parentIds = [];
        }else{
            $parentIds = explode(',', trim($userData->parentIds, ' ,'));
        }
        $parentIds[] = $userData->id;
        $this->data->parentIds = ','.join(',', $parentIds).',';
    }

    /**
     * handles the exception if its an duplication of the login field
     * @param ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey $e
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    protected function handleLoginDuplicates(ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey $e) {
        $errors = [
            'login' => []
        ];

        ZfExtended_UnprocessableEntity::addCodes([
            'E1094' => 'User can not be saved: the chosen login does already exist.',
            'E1095' => 'User can not be saved: the chosen userGuid does already exist.',
        ]);

        if($e->isInMessage("for key 'login'")) {
            $errors['login']['duplicateLogin'] = 'Dieser Anmeldename wird bereits verwendet.';
            $ecode = 'E1094';
        }
        elseif($e->isInMessage("for key 'userGuid'")) {
            $errors['login']['duplicateUserGuid'] = 'Diese UserGuid wird bereits verwendet.';
            $ecode = 'E1095';
        }
        else {
            throw $e; //otherwise throw this again
        }

        throw ZfExtended_UnprocessableEntity::createResponse($ecode, $errors);
    }

    /**
     * send a mail to user, if passwd has been reseted or account has been new created
     */
    protected function handlePasswdMail() {
        //convention for passwd being reset:
        if(property_exists($this->data, 'passwd') && is_null($this->data->passwd)) {
            $mailer = new ZfExtended_TemplateBasedMail();
            $mailer->sendToUser($this->entity);
        }
    }

    /**
     * resets the invalid login counter if password is changed of the user via User API (so at least an PM user) and not via "my settings"
     */
    protected function resetInvalidCounter() {
        //only if putAction was called directly, not via the authenticatedAction (my settings pw change)
        if($this->_request->getActionName() !== 'put' || empty($this->data->passwd)) {
            return;
        }
        $counter = ZfExtended_Factory::get('ZfExtended_Models_Invalidlogin', [$this->entity->getLogin()]);
        /* @var $counter ZfExtended_Models_Invalidlogin */
        $counter->resetCounter();
    }

    /**
     * checks if the loaded entity is editable, if not throw an exception
     * we decided to use a normal exception here, not a NotAllowedExeception
     * since editing a not editable user should not happen from frontend
     * @throws Zend_Exception
     */
    protected function checkIsEditable(){
        if(! $this->entity->getEditable()){
            throw new Zend_Exception('Tried to manipulate a not editable user');
        }
    }

    /***
     * Check in get/put/delete actions if the current logged in user is parent of the data(user)
     * which needs to be modified
     * @throws ZfExtended_NoAccessException
     */
    protected function checkUserAccessByParent(){
        //if current user has right seeAllUsers everything is OK
        if($this->isAllowed("backend", "seeAllUsers")) {
            return;
        }
        $userSession = new Zend_Session_Namespace('user');

        //if the edited user is the current user, also everything is OK
        if($userSession->data->userGuid == $this->entity->getUserGuid()) {
            return;
        }

        if($this->entity->hasParent($userSession->data->id)){
            return;
        }
        throw new ZfExtended_NoAccessException();
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

        if(!isset($this->data->roles)){
            return;
        }

        $this->data->roles = trim($this->data->roles, ',');

        //get the user old roles (put only)
        $oldRoles=[];
        if(isset($this->data->id)){
            $userModel=ZfExtended_Factory::get('ZfExtended_Models_User');
            /* @var $userModel ZfExtended_Models_User */
            $userModel->load($this->data->id);

            $oldRoles = $userModel->getRoles();
        }

        //if there are old roles, remove the roles for which the user isAllowed for setaclrole
        if(!empty($oldRoles)){
            $toRemove=[];
            foreach ($oldRoles as $old){
                $isAllowed=$this->isAllowed('setaclrole', $old);
                if($isAllowed){
                    $toRemove[]=$old;
                }
            }
            //remove the roles for which the user is allowed
            $oldRoles= array_diff($oldRoles,$toRemove);
        }

        $requestAclsArray=[];
        if(!empty($this->data->roles)){
            $requestAclsArray = explode(',',$this->data->roles);
        }

        //check if the user is allowed for the requested roles
        foreach ($requestAclsArray as $role){
            $isAllowed=$this->isAllowed('setaclrole', $role);
            if(!$isAllowed){
                throw new ZfExtended_NoAccessException("Authenticated User is not allowed to modify role ".$role);
            }
        }

        //after checking setaclrole the auto_set_role flag is evaluated,
        // that means the value defined in the `right` column is used as needed role for the `role` column
        $setAdditionally = $this->acl->getRightsToRolesAndResource($requestAclsArray, 'auto_set_role');

        //merge the old roles and the allowed roles from the request
        $requestAclsArray = array_unique(array_merge($requestAclsArray, $oldRoles, $setAdditionally));
        $this->data->roles = implode(',', $requestAclsArray);
    }

    /***
     * Check and update user session if the current modefied user is the one in the session
     */
    protected function checkAndUpdateSession(){
        $userSession = new Zend_Session_Namespace('user');
        //ignore the check if session user or the data user is not set
        if(!isset($userSession->data->id) || !isset($this->data->id)){
            return;
        }
        if($userSession->data->id==$this->data->id){
            $this->entity->setUserSessionNamespaceWithoutPwCheck($userSession->data->login);
        }
    }

    /***
     * The auto assignment with source and target language for the users is not supported. Please use the default user
     * assignment in the customers panel
     */
    protected function warnUserLanguages(){
        if((isset($this->data->sourceLanguage) && !empty($this->data->sourceLanguage))
        || (isset($this->data->targetLanguage) && !empty($this->data->targetLanguage))){
            $logger = Zend_Registry::get('logger');
            $logger->warn('E1347','Auto user assignment with defining source and target language for a user is no longer possible. Please use "user assoc default" api endpoint.');
            unset($this->data->sourceLanguage);
            unset($this->data->targetLanguage);
        }
    }
}