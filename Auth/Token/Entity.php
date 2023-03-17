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
class ZfExtended_Auth_Token_Entity extends ZfExtended_Models_Entity_Abstract
{

    protected $dbInstanceClass = "ZfExtended_Auth_Token_Db_Entity";
    protected $validatorInstanceClass = "ZfExtended_Auth_Token_Validator_Entity";

    /***
     * Create authentication token for given login and return the token
     * @param string $login
     * @param string $description
     * @return string the token with prefixed ID
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function create(string $login, string $description = ZfExtended_Auth_Token_Token::DEFAULT_TOKEN_DESCRIPTION): string
    {
        /** @var ZfExtended_Models_User $user */
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        $user->loadByLogin($login);

        $this->setUserId($user->getId());
        $this->setToken('Initial');
        $this->setDescription($description);
        //make a token
        $token = ZfExtended_Auth_Token_Token::generateAuthToken();
        //encrypt the token
        $this->setToken(ZfExtended_Authentication::getInstance()->createSecurePassword($token));
        $this->save();
        return $this->getId() . ZfExtended_Auth_Token_Token::TOKEN_SEPARATOR . $token;
    }
}
