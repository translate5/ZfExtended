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

class ZfExtended_Resource_DbConfig extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * internal options tree
     */
    protected array $dbOptionTree = [];

    /**
     * Pointer to the current data path used
     * @var array
     */
    protected $currentPath = null;

    protected ZfExtended_DbConfig_Type_Manager $typeManager;

    /***
     * List of all configs for there is no type-class found
     * @var array
     */
    protected array $typeClassNotFound = [];

    /**
     * (non-PHPdoc)
     * @see Zend_Application_Resource_Resource::init()
     */
    public function init()
    {
        //init some needed objects
        $bootstrap = $this->getBootstrap();
        /* $bootstrap Bootstrap */
        $app = $bootstrap->getApplication();

        $this->typeManager = new ZfExtended_DbConfig_Type_Manager();
        Zend_Registry::set('configTypeManager', $this->typeManager);

        //fetch all config from DB
        $dbConfig = ZfExtended_Factory::get('ZfExtended_Models_Config');
        /* @var $dbConfig ZfExtended_Models_Config */
        $entries = $dbConfig->loadAll();

        if (empty($entries)) {
            return;
        }
        $this->typeClassNotFound = [];

        foreach ($entries as $entry) {
            $this->addOneEntry($entry);
        }

        //update existing stored options
        $options = $app->mergeOptions($this->dbOptionTree, $app->getOptions());
        $bootstrap->setOptions($options);

        //update the registered config object
        Zend_Registry::set('config', new Zend_Config($options));

        // log all not found type classes for configs
        if (! empty($this->typeClassNotFound)) {
            $log = Zend_Registry::get('logger');
            $log->warn('E0000', 'Type class not found for config. See details for more info', [
                'configs' => implode(',', $this->typeClassNotFound),
            ]);
        }
    }

    public function initDbOptionsTree(array $dbConfigs)
    {
        $this->typeManager = Zend_Registry::get('configTypeManager');
        foreach ($dbConfigs as $cnf) {
            $this->addOneEntry($cnf);
        }
    }

    /**
     * adds a given db config entry to the internal config tree
     */
    protected function addOneEntry(array $entry)
    {
        $path = explode('.', $entry['name']);
        $this->currentPath = $entry['name'];
        $this->recursiveSetter($this->dbOptionTree, $path, $entry);
    }

    protected function recursiveSetter(array &$dbOptionTree, array $path, $entry)
    {
        $value = $entry['value'];
        $type = $entry['type'] ?? false;
        $key = array_shift($path);

        try {
            $configType = $this->typeManager->getType($entry['typeClass'] ?? null);
        } catch (Exception $exception) {
            $this->typeClassNotFound[] = $entry['name'] ?? null;
        }

        if ($configType === null) {
            // all not loaded type classes will be collected and logged after the config are loaded
            return;
        }

        if (empty($path)) {
            $dbOptionTree[$key] = $configType->convertValue($type, $value);
            settype($dbOptionTree[$key], $configType->getPhpType($type));

            return;
        }
        settype($dbOptionTree[$key], 'array');
        $this->recursiveSetter($dbOptionTree[$key], $path, $entry);
    }

    public function getDbOptionTree()
    {
        return $this->dbOptionTree;
    }
}
