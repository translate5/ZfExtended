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
 * @package portal
 * @version 2.0
 *
 */
/**
 */
class ZfExtended_Models_SystemRequirement_Validator {
    
    /**
     * Contains the Validation modules to be used.
     * @var array
     */
    protected static array $modules = [];
    
    protected array $results = [];
    
    protected $installationBootstrapOnly;
    
    public function __construct(bool $installationBootstrapOnly) {
        $this->installationBootstrapOnly = $installationBootstrapOnly;
        //load modules directory based
        if ($installationBootstrapOnly) {
            $sysPath = __DIR__.'/Modules';
            $modPath = 'application/modules/default/Models/SystemRequirement/Modules';
            $srcPath = 'application/modules/editor/src/Installer/SystemRequirement/Modules';
        } else {
            $sysPath = APPLICATION_ROOT.'/library/ZfExtended/Models/SystemRequirement/Modules';
            $modPath = APPLICATION_ROOT.'/application/modules/default/Models/SystemRequirement/Modules';
            $srcPath = APPLICATION_ROOT.'/application/modules/editor/src/Installer/SystemRequirement/Modules';
        }
        $this->addDefaultModules($sysPath, 'ZfExtended_Models_SystemRequirement_Modules_');
        $this->addDefaultModules($modPath, 'Models_SystemRequirement_Modules_');
        $this->addDefaultModules($srcPath, 'MittagQI\\Translate5\\Installer\\SystemRequirement\\Modules\\');

    }

    public static function addModule(string $name, string $className): void
    {
        self::$modules[$name] = $className;
    }

    protected function addDefaultModules($path, $classPath) {
        $foundModules = scandir($path);
        $newDefaultMods = [];
        foreach($foundModules as $module) {
            if($module == '.' || $module == '..' || str_ends_with($module, 'Abstract.php')) {
                continue;
            }
            require_once $path.'/'.$module;
            $module = preg_replace('/\.php$/', '', $module);
            $newDefaultMods[strtolower($module)] = $classPath.$module;
        }
        self::$modules = array_merge($newDefaultMods, self::$modules); //prepend new defaults to the start of the list
    }

    /**
     * Runs all or the given validation module
     * @param string|null $module
     * @return ZfExtended_Models_SystemRequirement_Result[]
     * @throws Exception
     */
    public function validate(string $module = null): array
    {
        $isInstallation = $this->installationBootstrapOnly;
        if (empty($module)) {
            $toRun = array_keys(self::$modules);
        } else {
            if (empty(self::$modules[$module])) {
                throw new Exception('SystemRequirement Module '.$module.' not found. Available modules: '.
                    print_r(self::$modules, true));
            }
            $toRun = [$module];
            //if a module is given, this is forced, also in installation
            $isInstallation = false;
        }
        foreach ($toRun as $module) {
            $moduleInstance = new self::$modules[$module];
            /* @var $moduleInstance ZfExtended_Models_SystemRequirement_Modules_Abstract */
            if ($isInstallation && !$moduleInstance->isInstallationBootstrap()) {
                continue;
            }
            if ($moduleInstance instanceof ZfExtended_Models_SystemRequirement_Modules_BulkAbstract) {
                $moduleInstance->validateBulk($this->results);
            } else {
                $this->results[$module] = $moduleInstance->validate();
            }
        }
        
        return $this->results;
    }
    
    public function add($name, $moduleClass) {
        self::$modules[$name] = $moduleClass;
    }
}