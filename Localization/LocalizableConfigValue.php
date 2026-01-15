<?php
/*
 START LICENSE AND COPYRIGHT

 This file is part of translate5

 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

namespace MittagQI\ZfExtended\Localization;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class LocalizableConfigValue implements ExtractableLocalization
{
    public function __construct(
        private string $configName,
        private string $columnName
    ) {
    }

    public function extract(string $className): array
    {
        if (empty($this->configName) || empty($this->columnName)) {
            throw new \Exception(
                'Empty configName or columnName not allowed for LocalizableConfigValue attribute in' . $className
            );
        }
        if ($this->columnName !== 'value' && $this->columnName !== 'default' && $this->columnName !== 'defaults') {
            throw new \Exception(
                'Wrong columnName for LocalizableConfigValue attribute, allowed are only ' .
                '"value", "default" and "defaults" but got " ' . $this->columnName . '" in ' . $className
            );
        }
        $strings = [];
        $sql = 'SELECT `' . $this->columnName . '`, `type`, `typeClass`' .
            ' FROM `Zf_configuration` WHERE `name` = \'' . $this->configName . '\'';
        $row = \Zend_Db_Table::getDefaultAdapter()->fetchRow($sql);

        if (! empty($row)) {
            /** @var \ZfExtended_DbConfig_Type_Manager $typeManager */
            $typeManager = \Zend_Registry::get('configTypeManager');
            $type = $typeManager->getType($row['typeClass']);

            if ($this->columnName === 'defaults') {
                $list = $type->getDefaultList($row['defaults']);
                $value = empty($list) ? [] : explode(',', $list);
            } else {
                $value = $type->convertValue($row['type'], $row[$this->columnName]);
            }
            if (! empty($value)) {
                if (is_array($value) || is_object($value)) {
                    if (is_object($value)) {
                        $value = get_object_vars($value);
                    }
                    foreach ($value as $item) {
                        $strings[] = $item;
                    }
                } else {
                    $strings[] = (string) $value;
                }
            }
        } else {
            error_log('Outdated LocalizableConfigValue attribute: ' . $this->configName);
        }

        return $strings;
    }
}
