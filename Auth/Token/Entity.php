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

/**
 * @method void setId(int $id)
 * @method string getId()
 * @method void setUserId(int $userId)
 * @method string getUserId()
 * @method void setDescription(string $description)
 * @method string getDescription()
 * @method void setToken(string $token)
 * @method string getToken()
 * @method void setCreated(string $created)
 * @method string getCreated()
 * @method void setExpires(string $expires)
 * @method string getExpires()
 */
class ZfExtended_Auth_Token_Entity extends ZfExtended_Models_Entity_Abstract
{
    protected $dbInstanceClass = "ZfExtended_Auth_Token_Db_Entity";

    protected $validatorInstanceClass = "ZfExtended_Auth_Token_Validator_Entity";

    protected array $publicColumns = ['token.id', 'description', 'created', 'expires'];

    /***
     * Create authentication token for given login and return the token
     *
     * @return string the token with prefixed ID
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function create(
        string $login,
        string $description = ZfExtended_Auth_Token_Token::DEFAULT_TOKEN_DESCRIPTION,
        ?DateTime $expires = null
    ): string {
        /** @var ZfExtended_Models_User $user */
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        $user->loadByLogin($login);

        $this->setUserId($user->getId());
        $this->setToken('Initial');
        $this->setDescription($description);
        if ($expires) {
            $this->setExpires($expires->format('Y-m-d H:i:s'));
        }
        //make a token
        $token = ZfExtended_Auth_Token_Token::generateAuthToken();
        //encrypt the token
        $this->setToken(ZfExtended_Authentication::getInstance()->createSecurePassword($token));
        $this->save();

        return $this->getId() . ZfExtended_Auth_Token_Token::TOKEN_SEPARATOR . $token;
    }

    public function loadAllForFrontEnd()
    {
        $s = $this->db
            ->select()
            ->setIntegrityCheck(false)
            ->from([
                'token' => $this->db->info($this->db::NAME),
            ], $this->publicColumns)
            ->join([
                'users' => 'Zf_users',
            ], 'users.id = token.userId', ['userGuid']);

        return $this->loadFilterdCustom($s);
    }

    public function loadAllForCli()
    {
        $s = $this->db
            ->select()
            ->setIntegrityCheck(false)
            ->from([
                'token' => $this->db->info($this->db::NAME),
            ], $this->publicColumns)
            ->join([
                'users' => 'Zf_users',
            ], 'users.id = token.userId', ['users.id as user_id']);

        return $this->loadFilterdCustom($s);
    }
}
