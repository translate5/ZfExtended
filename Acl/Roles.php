<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5

 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 translate5: Please see http://www.translate5.net/plugin-exception.txt or
 plugin-exception.txt in the root folder of translate5.

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
             http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

namespace MittagQI\ZfExtended\Acl;

/**
 * Hold's the basic roles
 */
class Roles
{
    public const SYSTEMADMIN = 'systemadmin';

    public const ADMIN = 'admin';

    public const API = 'api';

    public const PM = 'pm';

    public const CLIENTPM = 'clientpm';

    public const PMLIGHT = 'pmlight';

    public const EDITOR = 'editor';

    public const BASIC = 'basic';

    public const NORIGHTS = 'noRights';

    /**
     * Retrieves, if the passed roles identify a client-restricted role
     * Any customer-related data must be filtered to the bound customers of the user then
     */
    public static function isClientRestricted(array $userRoles): bool
    {
        return in_array(self::CLIENTPM, $userRoles) && ! in_array(self::PM, $userRoles);
    }

    /**
     * Removes dependant/subroles if the clientpm role is not set
     * or removes the client-pm-role if 'pm' is set
     */
    public static function filterRoles(array $userRoles): array
    {
        if (! static::isClientRestricted($userRoles)) {
            $newRoles = [];
            foreach ($userRoles as $role) {
                if (! str_starts_with($role, static::CLIENTPM)) {
                    $newRoles[] = $role;
                }
            }

            return $newRoles;
        }

        return $userRoles;
    }
}
