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

use MittagQI\Translate5\Acl\Roles;
use ReflectionClass;
use ReflectionException;

/**
 * common functions to read out the resource and right information
 */
class SetAclRoleResource extends AbstractResource
{
    /**
     * Acl Resource ID:
     */
    public const ID = 'setaclrole';

    public static string $roleDefinition = Roles::class;

    /**
     * @return RightDTO[]
     * @throws ReflectionException
     */
    public function getRights(): array
    {
        $refl = new ReflectionClass(self::$roleDefinition);
        $consts = $refl->getConstants();

        return $this->getRightsFromConstantList($consts, $refl);
    }
}
