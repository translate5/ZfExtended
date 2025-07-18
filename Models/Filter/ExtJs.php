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
class ZfExtended_Models_Filter_ExtJs extends ZfExtended_Models_Filter
{
    /**
     * decodes the filter/sort string, return always an array
     * @param string $todecode
     * @return array
     */
    protected function decode($todecode)
    {
        if (empty($todecode) || $todecode == '[]') {
            return [];
        }
        //if its a array we assume that it was already decoded
        if (is_array($todecode)) {
            return $todecode;
        }
        $filters = json_decode($todecode);
        if (empty($filters)) {
            // errors in parsing filters Filterstring: "{filter}"
            throw new ZfExtended_Models_Filter_Exception('E1220', [
                'filter' => $todecode,
            ]);
        }
        foreach ($filters as $filter) {
            if (is_object($filter) && isset($filter->table)) {
                unset($filter->table); //table string may not be set from outside for security reasons!
            }
        }

        return $filters;
    }

    /**
     * clean and apply the sort order to the given select
     */
    protected function applySort()
    {
        $cleanSort = [];
        foreach ($this->sort as $s) {
            $dir = strtolower($s->direction);
            $isProperty = $this->entity->hasField($s->property);
            $isMapped = ! empty($this->_sortColMap[$s->property]);
            if (($isProperty || $isMapped) && ($dir == 'asc' || $dir == 'desc')) {
                $cleanSort[] = $this->mapSort($s->property) . ' ' . $s->direction;
            }
        }
        $this->select->order($cleanSort);
    }

    /**
     * @throws Zend_Exception
     */
    protected function checkAndApplyOneFilter(stdClass $filter)
    {
        $this->initFilterData($filter);
        $this->checkField($filter);
        if (
            // no filter value
            ! isset($filter->value) ||
            // array without elements
            (is_array($filter->value) && empty($filter->value)) ||
            // explicit comparisions without value
            (in_array($filter->type, ['numeric', 'percent', 'date']) && $filter->value === '')
        ) {
            return;
        }
        $method = 'apply' . ucfirst($filter->type);

        //were assuming that all $methods are using the given field directly as
        //DB field name so we can merge the table alias as simple text
        $field = $this->getFullyQualifiedFieldname($filter, $filter->field);

        switch ($filter->type) {
            case 'orExpression':
                $this->applyExpression($filter, true);

                break;
            case 'andExpression':
                $this->applyExpression($filter, false);

                break;
            case 'notIsNull':
                $this->applyNotIsNull($field);

                break;
            case 'isNull':
                $this->applyIsNull($field);

                break;
            case 'percent':
                $method = 'applyPercent_' . $filter->comparison;
                $this->$method($field, $filter->value, $this->getFullyQualifiedFieldname($filter, $filter->totalField));

                break;
            case 'numeric':
                $method = 'applyNumeric_' . $filter->comparison;
                $this->$method($field, $filter->value);

                break;
            case 'date':
                //to be used for date comparsion "day" based, so additional times are just ignored (in filter and data).
                //for datetime (including the time part) comparsion just use numeric above!
                $method = 'applyDate_' . $filter->comparison;
                $this->$method($field, $filter->value);

                break;
            case 'list':
            case 'notInList':
            case 'listAsString':
            case 'listCommaSeparated':
                settype($filter->value, 'array');
                // no break
            case 'string':
            case 'boolean':
                $this->$method($field, $filter->value);

                return;
            default:
                //illegal type in filter
                throw new ZfExtended_Models_Filter_Exception('E1221', [
                    'type' => $filter->type,
                ]);
        }
    }

    /**
     * (non-PHPdoc)
     * @see ZfExtended_Models_Filter::applyExpression()
     */
    protected function applyExpression(stdClass $field, $isOr = true)
    {
        settype($field->value, 'array');

        //populate the internal vars
        $select = ZfExtended_Factory::get('Zend_Db_Select', [$this->select->getAdapter()]);

        $subFilter = ZfExtended_Factory::get(get_class($this), [
            $this->entity,
            $field->value,
        ]);
        /* @var $subFilter ZfExtended_Models_Filter_ExtJs */
        $subFilter->whereOp = $isOr ? 'orWhere' : 'where';

        //start recursive walk through the OR filters
        $subFilter->applyToSelect($select, false);

        $this->where(join(' ', $select->getPart($select::WHERE)));
    }

    /**
     * inits the fields ofthe anonymous filter object
     */
    protected function initFilterData(stdClass $filter)
    {
        if ($filter->type instanceof ZfExtended_Models_Filter_JoinAbstract) {
            $join = $filter->type;
            $join->mergeFilter($filter);
            $join->configureEntityFilter($this);
        }
        settype($filter->type, 'string');
        settype($filter->field, 'string');
        settype($filter->comparison, 'string');
        //override filter table only if not set explicitly
        if (empty($filter->table) && isset($this->fieldTableMap[$filter->field])) {
            $filter->table = $this->fieldTableMap[$filter->field];
        } else {
            settype($filter->table, 'string');
        }
        if (! isset($filter->value)) {
            $filter->value = null;
        }
    }

    /**
     * check if field name is valid and field exists in entity
     * @throws Zend_Exception
     */
    protected function checkField(stdClass $filter)
    {
        $field = $filter->field;
        $isExpression = $filter->type == 'orExpression' || $filter->type == 'andExpression';
        if (isset($filter->type) && $isExpression && empty($field)) {
            return;
        }
        if (! preg_match('/[a-z0-9-_]+/i', $field)) {
            //Illegal chars in field name "{field}"
            throw new ZfExtended_Models_Filter_Exception('E1222', [
                'field' => $field,
            ]);
        }
        if (empty($filter->table) && ! $this->entity->hasField($field)) {
            //Illegal field "{field}" requested
            throw new ZfExtended_Models_Filter_Exception('E1223', [
                'field' => $field,
            ]);
        }
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyNumeric_lt($field, $value)
    {
        $this->where($field . ' < ?', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyNumeric_gt($field, $value)
    {
        $this->where($field . ' > ?', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyNumeric_lteq($field, $value)
    {
        $this->where($field . ' <= ?', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyNumeric_gteq($field, $value)
    {
        $this->where($field . ' >= ?', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyNumeric_eq($field, $value)
    {
        $this->where($field . ' = ?', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyDate_lt($field, $value)
    {
        $this->where('date(' . $field . ') < date(?)', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyDate_gt($field, $value)
    {
        $this->where('date(' . $field . ') > date(?)', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyDate_lteq($field, $value)
    {
        $this->where('date(' . $field . ') <= date(?)', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyDate_gteq($field, $value)
    {
        $this->where('date(' . $field . ') >= date(?)', $value);
    }

    /**
     * @param string $field
     * @param int $value
     */
    protected function applyDate_eq($field, $value)
    {
        $this->where('date(' . $field . ') = date(?)', $value);
    }

    /**
     * apply the the lt percent filter to the select
     * @param string $field  FIXME SQL INJECTION?
     * @param int $value
     * @param string $totalField   FIXME SQL INJECTION?
     */
    protected function applyPercent_lt($field, $value, $totalField)
    {
        $this->where('IFNULL(((' . $field . '/' . $totalField . ')*100),0) < ?', $value);
    }

    /**
     * apply the the gt percent filter to the select
     * @param string $field
     * @param int $value
     * @param string $totalField
     */
    protected function applyPercent_gt($field, $value, $totalField)
    {
        $this->where('IFNULL(((' . $field . '/' . $totalField . ')*100),0) > ?', $value);
    }

    /**
     * apply the eq percent filter to the select
     * @param string $field
     * @param int $value
     * @param string $totalField
     */
    protected function applyPercent_eq($field, $value, $totalField)
    {
        $this->where('IFNULL(((' . $field . '/' . $totalField . ')*100),0) = ?', $value);
    }

    /**
     * @param string $field
     * @param string $value
     */
    protected function applyString($field, $value)
    {
        $value = $this->escapeMysqlWildcards($value);
        $this->where($field . ' like ?', '%' . $value . '%');
    }

    /**
     * @param string $field
     */
    protected function applyIsNull($field)
    {
        $this->where($field . ' is null');
    }

    /**
     * @param string $field
     */
    protected function applyNotIsNull($field)
    {
        $this->where('not ' . $field . ' is null');
    }

    /**
     * @param string $field
     */
    protected function applyList($field, array $values)
    {
        $this->where($field . ' in (?)', $values);
    }

    /**
     * @param string $field
     */
    protected function applyNotInList($field, array $values)
    {
        $this->where($field . ' not in (?)', $values);
    }

    /**
     * Converts a list filter based on a string search
     * TODO FIXME: this is very dirty and will not work e.g. with find 9 in ',7,12,29,'
     * - Filter type does not come from native ExtJs
     * - Filter type is set by mapping
     */
    protected function applyListAsString(string $field, array $values)
    {
        $db = Zend_Registry::get('db');
        $where = [];
        foreach ($values as $value) {
            $where[] = $db->quoteInto($field . ' like ?', '%' . $value . '%');
        }
        $this->where(implode(' OR ', $where));
    }

    /**
     * Converts a list filter based on a string search,
     * where the search values are surrounded with comma
     *
     * - Filter type does not come from native ExtJs
     * - Filter type is set by mapping
     */
    protected function applyListCommaSeparated(string $field, array $values)
    {
        //add commas before and after each value
        $this->applyListAsString($field, array_map(function ($item) {
            return ',' . $item . ',';
        }, $values));
    }

    /**
     * @throws Zend_Db_Select_Exception
     */
    private function getFullyQualifiedFieldname(stdClass $filter, string $field): string
    {
        if (! empty($filter->table)) {
            $field = '`' . $filter->table . '`.' . $field;
        } elseif (! empty($this->defaultTable)) {
            $field = '`' . $this->defaultTable . '`.' . $field;
        } else {
            $table = $this->getEntityTable();
            $alias = null;
            foreach ($this->select->getPart('from') as $_alias => $info) {
                if ($table == $info['tableName']) {
                    $alias = $_alias;
                }
            }
            $field = '`' . ($alias ?? $table) . '`.' . $field;
        }

        return $field;
    }
}
