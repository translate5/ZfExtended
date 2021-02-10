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
    protected $modules = [];
    
    protected $results = [];
    
    protected $installationBootstrapOnly;
    
    public function __construct(bool $installationBootstrapOnly) {
        $this->installationBootstrapOnly = $installationBootstrapOnly;
        //load modules directory based
        $this->addModules(__DIR__.'/Modules', 'ZfExtended_Models_SystemRequirement_Modules_');
        $this->addModules('application/modules/default/Models/SystemRequirement/Modules', 'Models_SystemRequirement_Modules_');
        
    }
    
    protected function addModules($path, $classPath) {
        $foundModules = scandir($path);
        foreach($foundModules as $module) {
            if($module == '.' || $module == '..' || $module == 'Abstract.php') {
                continue;
            }
            require_once $path.'/'.$module;
            $module = preg_replace('/\.php$/', '', $module);
            $this->modules[strtolower($module)] = $classPath.$module;
        }
    }
    
    /**
     * Runs all or the given validation module
     * @param string $module
     * @throws Exception
     */
    public function validate(string $module = null) {
        $isInstallation = $this->installationBootstrapOnly;
        if(empty($module)) {
            $toRun = array_keys($this->modules);
        }
        else {
            if(empty($this->modules[$module])) {
                throw new Exception('SystemRequirement Module '.$module.' not found. Available modules: '.print_r($this->modules,1));
            }
            $toRun = [$module];
            //if a module is given, this is forced, also in installation
            $isInstallation = false;
        }
        foreach($toRun as $module) {
            $moduleInstance = new $this->modules[$module];
            /* @var $moduleInstance ZfExtended_Models_SystemRequirement_Modules_Abstract */
            if($isInstallation && !$moduleInstance->isInstallationBootstrap()) {
                continue;
            }
            $this->results[$module] = $moduleInstance->validate();
        }
        
        return $this->results;
    }
    
    public function add($name, $moduleClass) {
        $this->modules[$name] = $moduleClass;
    }
}