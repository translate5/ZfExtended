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
/**
 * handles invalid logins
 */
class ZfExtended_Models_Invalidlogin extends ZfExtended_Models_Db_Invalidlogin
{
    private int $maximum;

    /**
     * @throws Zend_Exception
     */
    public function __construct(?int $maximum = null)
    {
        $config = Zend_Registry::get('config');
        if ($maximum === null) {
            $maximum = $config->runtimeOptions?->invalidLogin?->maximum ?? 3;
        }
        $this->maximum = (int) $maximum;

        parent::__construct();
    }

    /**
     * increments the invalid logins of a login
     */
    public function increment(string $login): void
    {
        $this->insert(
            [
                'login' => $login,
            ]
        );
    }

    public function resetCounter(string $login): void
    {
        $where = $this->getAdapter()->quoteInto('login = ?', $login);
        $this->delete($where);
    }

    /**
     * @throws Zend_Db_Select_Exception
     */
    public function hasMaximumInvalidations(string $login): bool
    {
        //before max check, we clean older than a day:
        $this->delete("created < '" . date('Y-m-d h:i:s', time() - 24 * 3600) . "'");

        $row = $this->fetchRow(
            $this->select([
                'invalidlogins' => 'COUNT(*)',
            ])
                ->reset(Zend_Db_Select::COLUMNS)
                ->columns([
                    'invalidlogins' => 'COUNT(*)',
                ])
                ->where('login = ? and created > (NOW() - INTERVAL 1 DAY)', $login)
        );

        return $row['invalidlogins'] >= $this->maximum;
    }

    public function loadInvalidLogins(string $login): array
    {
        return $this->fetchAll($this->select()->where('login = ?', $login))->toArray();
    }
}
