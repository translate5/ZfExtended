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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */

use MittagQI\ZfExtended\Session\SessionInternalUniqueId;

/**
 * handles passwd reset
 *
 * @method void setId(int $id)
 * @method void setUserId(int $userId)
 * @method void setExpiration(int $expiration)
 * @method void setResetHash(string $resetHash)
 * @method void setInternalSessionUniqId(string $uniqueId)
 *
 * @method string getId()
 * @method string getUserId()
 * @method string getExpiration()
 * @method string getResetHash()
 * @method string getInternalSessionUniqId()
 */
class ZfExtended_Models_Passwdreset extends ZfExtended_Models_Entity_Abstract
{
    protected $dbInstanceClass = 'ZfExtended_Models_Db_Passwdreset';

    protected $validatorInstanceClass = 'ZfExtended_Models_Validator_Passwdreset';

    public function deleteOldHashes()
    {
        $all = $this->loadAll();
        foreach ($all as $key => $row) {
            if ($row['expiration'] < time()) {
                $this->load($row['id']);
                $this->delete();
            }
        }
    }

    /**
     * @param string $hash
     * @return boolean
     */
    public function hashMatches($hash)
    {
        try {
            $s = $this->db->select();
            $s->where('resetHash = ?', $hash)
                ->where('internalSessionUniqId = ?', SessionInternalUniqueId::getInstance()->get());
            $this->loadRowBySelect($s);
        } catch (ZfExtended_Models_Entity_NotFoundException $exc) {
            return false;
        }

        return true;
    }

    /**
     * reset password
     * @return boolean
     * @throws ReflectionException
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ZfExtended_ValidateException
     */
    public function reset(string $login, string $origin): bool
    {
        $session = new Zend_Session_Namespace();
        $user = ZfExtended_Factory::get(ZfExtended_Models_User::class);

        try {
            $user->loadRow('login = ?', $login);
        } catch (ZfExtended_Models_Entity_NotFoundException $exc) {//catch the 404 thrown, if no user found
            return false;
        }
        $session->resetHash = md5(ZfExtended_Utils::uuid());

        /* @var ZfExtended_Logger $log */
        $log = Zend_Registry::get('logger');
        $log->info('E1556', 'Password reset hash requested for user {login}', [
            'login' => $login,
            'origin' => $origin,
        ]);

        $this->setUserId($user->getId());
        $this->setResetHash($session->resetHash);
        $this->setExpiration(time() + 1800);
        $this->setInternalSessionUniqId(SessionInternalUniqueId::getInstance()->get());

        $this->validate();
        $this->save();

        $mailer = new ZfExtended_TemplateBasedMail();
        $mailer->setParameters([
            'resetHash' => $session->resetHash,
        ]);
        $mailer->sendToUser($user);

        return true;
    }
}
