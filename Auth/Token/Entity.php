<?php 
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5

 Copyright (c) 2013 - 2022 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 translate5 plug-ins that are distributed under GNU AFFERO GENERAL PUBLIC LICENSE version 3:
 Please see http://www.translate5.net/plugin-exception.txt or plugin-exception.txt in the root
 folder of translate5.

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
             http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/
/***
* @method void setId() setId(int $id)
* @method int getId() getId()
* @method void setUserId() setUserId(int $userId)
* @method int getUserId() getUserId()
* @method void setDescription() setDescription(string $description)
* @method string getDescription() getDescription()
* @method void setToken() setToken(string $token)
* @method string getToken() getToken()
* @method void setCreated() setCreated(string $created)
* @method string getCreated() getCreated()
* @method void setExpires() setExpires(string $expires)
* @method string getExpires() getExpires()
*/

class ZfExtended_Auth_Token_Entity extends ZfExtended_Models_Entity_Abstract {

    protected $dbInstanceClass = "ZfExtended_Auth_Token_Db_Entity";
    protected $validatorInstanceClass = "ZfExtended_Auth_Token_Validator_Entity";

    private const DEFAULT_TOKE_DESCRIPTION = 'Default';

    public const TOKEN_SEPARATOR = ':';

    /***
     * Load row by given user login
     * @param string $login
     * @return Zend_Db_Table_Row_Abstract|null
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function loadByLogin(string $login){
        /** @var ZfExtended_Models_User $user */
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        $user->loadByLogin($login);

        $s = $this->db->select();
        $s->where('userId=?',$user->getId());
        $row=$this->db->fetchRow($s);
        if(empty($row)){
            $this->notFound(__CLASS__ . '#login', $login);
        }
        $this->row =$row;
        return $row;
    }

    /***
     *
     * @return string
     * @throws Exception
     */
    public function generateAuthToken(string $prefix): string
    {
        return $prefix . self::TOKEN_SEPARATOR . bin2hex(random_bytes(16));
    }


    /***
     * Create authentication token for given login and return the token
     * @param string $login
     * @return string
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function create(string $login){
        /** @var ZfExtended_Models_User $user */
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        $user->loadByLogin($login);

        $this->setUserId($user->getId());
        $this->setToken('Initial');
        $this->setDescription(self::DEFAULT_TOKE_DESCRIPTION);
        $id = $this->save();
        $token = $this->generateAuthToken($id);
        $this->setToken(ZfExtended_Authentication::getInstance()->createSecurePassword($token));
        $this->save();
        return $token;
    }
}
