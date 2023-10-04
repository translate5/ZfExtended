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

use editor_Models_Config;
use ReflectionClass;

/**
 * common functions to read out the resource and right information
 */
class ConfigLevelResource extends AbstractResource {
    /**
     * resource ID for the acl config level record
     * @var string
     */
    public const ID = 'applicationconfigLevel';

    /**
     * @return RightDTO[]
     */
    public function getRights(): array
    {
        $config = new editor_Models_Config();
        $refl = new ReflectionClass($config);
        $consts = $refl->getConstants();

        $consts = array_filter($consts, fn($key) => str_starts_with($key, 'CONFIG_LEVEL_'), ARRAY_FILTER_USE_KEY);
        $consts = array_map(fn($v) => $config->getConfigLevelLabel($v), $consts);
        return $this->getRightsFromConstantList($consts, $refl);
    }
}