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
declare(strict_types=1);

namespace MittagQI\ZfExtended\Acl;

use MittagQI\Translate5\Test\Api\Exception;

/**
 * Holds all available resource definitions
 */
class ResourceManager
{
    /**
     * @var array<string, bool>
     */
    private static array $resourceDefinitions = [
        ConfigLevelResource::class => true,
        ConfigRestrictionResource::class => true,
        AutoSetRoleResource::class => true,
        SetAclRoleResource::class => true,
        SystemResource::class => false,
    ];

    /**
     * @param bool $internal internal rights are not delivered into the UI for usage there
     */
    public static function registerResource(string $classname, bool $internal = false): void
    {
        self::$resourceDefinitions[$classname] = $internal;
    }

    /**
     * @return ResourceInterface[]
     * @throws Exception
     */
    public static function getBusinessLogicResources(): array
    {
        $result = [];
        foreach (self::$resourceDefinitions as $cls => $internal) {
            if ($internal === true) {
                continue;
            }
            /* @var ResourceInterface $resource */
            if (method_exists($cls, 'getInstance')) {
                $resource = $cls::getInstance();
            } else {
                $resource = new $cls();
            }
            $result[] = $resource;
        }

        return $result;
    }

    /**
     * @return RightDTO[]
     * @throws Exception
     */
    public static function getAllRights(): array
    {
        $rightCount = 0;
        $result = [];
        foreach (self::$resourceDefinitions as $cls => $internal) {
            /* @var AbstractResource $resource */
            if (method_exists($cls, 'getInstance')) {
                $resource = $cls::getInstance();
            } else {
                $resource = new $cls();
            }
            $rights = $resource->getRights();
            $rightCount += count($rights);
            $result = array_merge($result, $rights);
        }

        if ($rightCount !== count($result)) {
            //FIXME currently the right names must be unique in the application.
            // So we should bring als the resource ID into the UI to solve that
            throw new Exception('Non unique rights used!');
        }

        return $result;
    }
}
