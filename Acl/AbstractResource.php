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

use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;

/**
 * common functions to read out the resource and right information
 */
abstract class AbstractResource implements ResourceInterface {

    public function getId(): string {
        return static::ID;
    }

    /**
     * @return RightDTO[]
     */
    public function getRights(): array
    {
        $refl = new ReflectionClass($this);
        $consts = $refl->getConstants();
        return $this->getRightsFromConstantList($consts, $refl);
    }

    /**
     * @param array $consts
     * @param ReflectionClass $refl
     * @return array
     */
    protected function getRightsFromConstantList(array $consts, ReflectionClass $refl): array
    {
        $factory = DocBlockFactory::createInstance();
        $result = [];
        foreach ($consts as $key => $val) {
            //ignore the resource ID itself
            if ($key === 'ID') {
                continue;
            }
            $acl = new RightDTO();
            $acl->id = $key;
            $acl->name = $val;
            $acl->resource = $this->getId();
            $acl->description = $this->getDescription($acl, $refl, $factory);
            $result[] = $acl;
        }
        return $result;
    }

    /**
     * @param RightDTO $acl
     * @param ReflectionClass $refl
     * @param DocBlockFactory $factory
     * @return string
     */
    private function getDescription(RightDTO $acl, ReflectionClass $refl, DocBlockFactory $factory): string
    {
        $constantReflection = $refl->getReflectionConstant($acl->id);
        if ($constantReflection === false) {
            return 'COULD NOT GET CLASS REFLECTION FOR ' . $acl->id;
        }
        $docBlock = $constantReflection->getDocComment();
        if (empty($docBlock)) {
            return 'NO DOCBLOCK GIVEN FOR ' . $acl->id;
        }
        $docBlock = $factory->create($docBlock);
        $result = trim($docBlock->getSummary());
        if (strlen($result) === 0) {
            return 'NO DOCBLOCK DESCRIPTION GIVEN FOR ' . $acl->id;
        }
        return $result;
    }
}
