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

namespace MittagQI\ZfExtended\Access;

use stdClass;
use ZfExtended_Models_Entity_Abstract;
use ZfExtended_Models_Filter;
use ZfExtended_Models_Filter_ExtJs6;
use ZfExtended_Models_Filter_Join;

/**
 * Implements the user-role driven client-restriction
 * Currently just a simple filtering for customer-id or filtering a customer-assoc table via simple join
 * Can be used in all entity-classes if neccessary by adding the $clientAccessRestriction config there
 */
final class ClientRestriction
{
    public function __construct(private array $config)
    {
    }

    /**
     * Adds the client-restriction to the passed filter
     * This method expects a filter to be set by the controller, e.g. the fields defined there may e.g. represent a join in case of an assoc
     * @param ZfExtended_Models_Filter $filter
     * @param array $clientIds
     * @return void
     */
    public function apply(ZfExtended_Models_Filter $filter, array $clientIds): void
    {
        $data = $this->createFilterData($clientIds);
        $foundFilter = null;
        // an assoc-connection will always be made new as filter-values usually do not filter for IDs
        if (!array_key_exists('assoc', $this->config) && $filter->hasFilter($data->field, $foundFilter) && $foundFilter->type === $data->type) {

            $foundFilter->value = $this->intersectValue($foundFilter->value, $data->value, $foundFilter->type);

        } else {

            $filter->addFilter($data);
        }
    }

    /**
     * @param ZfExtended_Models_Entity_Abstract $entity
     * @param array $clientIds
     * @return ZfExtended_Models_Filter
     */
    public function create(ZfExtended_Models_Entity_Abstract $entity, array $clientIds): ZfExtended_Models_Filter
    {
        $filter = new ZfExtended_Models_Filter_ExtJs6($entity);
        $filter->addFilter($this->createFilterData($clientIds));
        return $filter;
    }

    /**
     * Creates addable filter-data
     * @param array $clientIds
     * @return stdClass
     */
    private function createFilterData(array $clientIds): stdClass
    {
        $data = new stdClass();
        $data->field = $this->getConfiguredField();
        // handling an association-table: further configuration, error-prone :(
        // naming matches the naming of a join-filter
        if (array_key_exists('assoc', $this->config)) {
            // config is like ['type' => 'list', 'assoc' => ['table' => 'assocTable', 'foreignKey' => 'entityTableId', 'localKey' => 'id', 'searchKey' => 'customerId']]
            $localKey = array_key_exists('localKey', $this->config['assoc']) ? $this->config['assoc']['localKey'] : 'id';
            $searchField = array_key_exists('searchField', $this->config['assoc']) ? $this->config['assoc']['searchField'] : 'customerId';
            $data->type = new ZfExtended_Models_Filter_Join($this->config['assoc']['table'], $searchField, $this->config['assoc']['foreignKey'], $localKey, $this->getConfiguredType());
        } else {
            $data->type = $this->getConfiguredType();
            $data->comparison = 'in';
        }
        // The passed client-id's may be empty. This can happen, if a "PM all clients" or admin removes all customers a clientPM is bound to
        // To keep the functionality of the value-driven filter-API, we then use the non-existing id "0"
        $data->value = empty($clientIds) ? [0] : $clientIds;
        return $data;
    }

    /**
     * Creates the resulting value in case we combine a filter
     * @param mixed $current
     * @param array $clientIds
     * @param string $type
     * @return mixed
     */
    private function intersectValue(mixed $current, array $clientIds, string $type): mixed
    {
        $foundVal = ($type === 'string') ?
            explode(',', $current)
            : (is_array($current) ? $current : [$current]);
        // we use the difference as the new value
        $newVal = array_values(array_intersect($foundVal, $clientIds));
        // if the intersection is empty, we search for a non-existing customer to not break the Select
        if (empty($newVal)) {
            $newVal = [0];
        }
        if ($type === 'string') {
            $newVal = implode(',', $newVal);
        }
        return $newVal;
    }

    private function getConfiguredField(): string
    {
        return array_key_exists('field', $this->config) ? $this->config['field'] : 'customerId';
    }

    private function getConfiguredType(): string
    {
        return array_key_exists('type', $this->config) ? $this->config['type'] : 'list';
    }
}
