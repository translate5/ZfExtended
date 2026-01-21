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

/**
 * converts the given Filter and Sort String from ExtJS to an object structure appliable to a Zend Select Object
 * @author Marc Mittag
 */
class ZfExtended_Models_Filter_ExtJs5 extends ZfExtended_Models_Filter_ExtJs
{
    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_LIKE = 'like';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_NOTINLIST = 'notInList';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_IN = 'in';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_EQ = 'eq';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_GT = 'gt';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_GTEQ = 'gteq';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_LT = 'lt';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_LTEQ = 'lteq';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_IS = '=';

    #[MittagQI\ZfExtended\Localization\LocalizableProp]
    public const OPERATOR_IDENTICAL = '==';
    /**
     * This list contains a mapping between new ExtJS 5 operator parameters (key)
     * to the old ExtJS 4 type parameters (value)
     * @var array
     */
    protected $operatorToType = [
        self::OPERATOR_LIKE => 'string',
        self::OPERATOR_NOTINLIST => 'notInList',
        self::OPERATOR_IN => 'list',
        self::OPERATOR_EQ => 'numeric',
        self::OPERATOR_GT => 'numeric',
        self::OPERATOR_GTEQ => 'numeric',
        self::OPERATOR_LT => 'numeric',
        self::OPERATOR_LTEQ => 'numeric',
        self::OPERATOR_IS => 'boolean',
        self::OPERATOR_IDENTICAL => 'boolean',
    ];

    /**
     * converts the new ExtJS 5 filter format to the old ExtJS 4 format
     *
     * @param string $todecode
     * @return array
     */
    protected function decode($todecode)
    {
        $filters = parent::decode($todecode);
        foreach ($filters as $key => $filter) {
            $filters[$key] = $this->convert($filter);
        }

        return $filters;
    }

    /**
     * Convertion Method
     * @throws ZfExtended_Exception
     * @return stdClass
     */
    protected function convert(stdClass $filter)
    {
        //is a sort, do nothing more here
        if (empty($filter->operator) && isset($filter->direction)) {
            return $filter;
        }
        if (! isset($filter->property)) {
            return $filter;
        }
        $filter->field = $filter->property;
        unset($filter->property);
        if (empty($this->operatorToType[$filter->operator])) {
            //Unkown filter operator "{operator}" from ExtJS 5 Grid Filter!
            throw new ZfExtended_Models_Filter_Exception('E1224', [
                'operator' => $filter->operator,
            ]);
        }
        $filter->type = $this->operatorToType[$filter->operator];
        if ($filter->type == 'numeric') {
            $filter->comparison = $filter->operator;
        }
        if ($filter->type == 'boolean') {
            $filter->comparison = 'eq';
            $filter->type = 'numeric';
            $filter->value = (isset($filter->value) && $filter->value ? 1 : 0);
        }
        unset($filter->operator);

        return $filter;
    }
}
