<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
/**
 * Singleton-Instanz
 *
 * - zu beziehen über $this->getInstance
 * - bezieht seine Definitionen aus der modulName/configs/aclConfig.ini
 *
 *
 */
class ZfExtended_Acl extends Zend_Acl {
    /**
     * Singleton Instanzen
     *
     * @var array _instances enthalten ACL Objekte
     */
    protected static $_instance = null;

    /**
     * Singleton Instanz - Hole Acl-Instanz
     *
     * - prüft, ob bereits eine Instanz erstellt wurde;
     *   falls ja, wird diese zurückgegeben
     *
     * @param boolean $init causes getInstance, to create the singleton new
     * @return ZfExtended_Acl
     */
    public static function getInstance($init = false)
    {
        if (null === self::$_instance || $init) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    /**
     * Singleton Instanz auf NULL setzen, um sie neu initialiseren zu können
     *
     * @return void
     */
    public static function reset() {
        self::$_instance = NULL;
    }

    /**
     * Creates a ACL with roles, resources and rights/privileges
     * - must be used as singleton (getInstance())
     * - only allow rules are set, default is deny
     */
    protected function __construct() {
        $db = ZfExtended_Factory::get('ZfExtended_Models_Db_AclRules');
        /* @var $db ZfExtended_Models_Db_AclRules */
        
        $roles = $db->loadRoles(Zend_Registry::get('module'));
        $this->addRoles($roles);
        
        $resources = $db->loadResources(Zend_Registry::get('module'));
        $this->addResources($resources);

        $rules = $db->loadByModule(Zend_Registry::get('module'));
        $this->addRules($rules);
    }
    
    /**
     * Return all roles, not only the ones from the current module
     * @return array
     */
    public function getAllRoles() {
        $db = ZfExtended_Factory::get('ZfExtended_Models_Db_AclRules');
        /* @var $db ZfExtended_Models_Db_AclRules */
        $s = $db->select()
        ->from($db->info($db::NAME), 'role')
        ->distinct();
        return array_column($db->fetchAll($s)->toArray(), 'role');
    }
    
    /**
     * checks if one of the passed roles allows the resource / privelege
     *
     * @param  array     $roles
     * @param  Zend_Acl_Resource_Interface|string $resource
     * @param  string                             $privilege
     * @return boolean
     */
    public function isInAllowedRoles(array $roles, $resource = null, $privilege = null){
        $allowed = false;
        foreach ($roles as $role){
            try {
                if($this->isAllowed($role,$resource,$privilege)){
                    $allowed = true;
                }
            } catch (Zend_Acl_Role_Registry_Exception $exc) {
                //if role is not registered because it is the role of a different module
            } catch (Zend_Acl_Exception $e) {
                //if the resource is not registered access is false 
                if(preg_match("/Resource '[^']*' not found/", $e->getMessage())) {
                    return false;
                }
                throw $e;
            }
        }
        return $allowed;
    }
    
    /**
     * Checks if the configured rights were valid
     */
    public function checkRights() {
        if(!ZfExtended_Debug::hasLevel('core', 'acl')) {
            return;
        }
        $this->getControllers();
        $allowedResource = ['frontend', 'backend', 'setaclrole'];
        foreach($this->_allRules as $rule) {
            if(in_array($rule['resource'], $allowedResource)) {
                continue;
            }
            $isAll = $rule['right'] == 'all';
            $isClass = class_exists($rule['resource'], true);
            $isController = class_exists($rule['resource'].'Controller', true);
            //class_exists loads the class and isAll is set then its OK
            if(($isClass || $isController) && $isAll) {
                continue;
            }
            if(method_exists($rule['resource'].'Controller', $rule['right'].'Action')){
                continue;
            }
            if(method_exists($rule['resource'], $rule['right'])){
                continue;
            }
            error_log('The following ACL resource and privilege does not exist in the Application: '.$rule['resource'].'; '.$rule['right']);
        }
    }

    /**
     * Add the given rules to the internal ACL instance
     * @param array $rules
     * @throws Zend_Exception
     */
    protected function addRules(array $rules){
        if(empty($rules)) {
            return;
        }
        $this->_allRules = $rules;
        foreach($rules as $rule) {
            $role = $rule['role'];
            $right = $rule['right'];
            $resource = $rule['resource'];
            if ($right == 'all') {
                if($resource == 'frontend'){
                    throw new Zend_Exception('For the resource "frontend" the right "all" can not be used!');
                }
                $this->allow($role, $resource);
                continue;
            }
            $this->allow($role, $resource, $right);
        }
    }
    
    /**
     * Adds the resources to the internal ACL instance
     * @param array $resources
     */
    protected function addResources(array $resources){
        foreach ($resources as $resource) {
            $this->addResource(new Zend_Acl_Resource($resource));
        }
    }
    
    /**
     * Adds the roles to the internal ACL instance
     * @param array $roles
     */
    protected function addRoles(array $roles){
        foreach ($roles as $role) {
            $const = 'ACL_ROLE_'.strtoupper($role);
            if(!defined($const)) {
                define($const, $role);
            }
            $this->addRole(new Zend_Acl_Role($role));
        }
    }
    
    /**
     * Holt alle Controller aller Module
     *
     * - nochmals separat in Portal_AclTest analog implementiert, um sie hier private deklarieren zu können
     *
     * @return array array('moduleName'=>array('controllerName1','controllerName2',...),...)
     */
    protected function getControllers (){
        $controllers = array();
        $module = Zend_Registry::get('module');
        $controllerDirs = Zend_Controller_Front::getInstance()->getControllerDirectory();
        
        $this->includeController($controllerDirs[$module]);
        foreach($controllerDirs as $mod => $dir) {
            if(substr($mod, 0, 8) === '_plugins') {
                $this->includeController($dir);
            }
        }
        
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'Zend_Controller_Action') && preg_match("'Controller$'",$class)) {
                $controllers[] = strtolower(substr($class, 0, strpos($class, "Controller")));
            }
        }
        return $controllers;
    }
    
    protected function includeController($path) {
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS));
        foreach($objects as $file => $object){
            if (strstr($file.'#', "Controller.php#") !== false) {
                include_once $file;
            }
        }
    }
    
    /**
     * Returns a list of frontend privileges / rights to the given roles
     * @param array $roles
     */
    public function getFrontendRights(array $roles) {
        $result = array();
        foreach($roles as $role) {
            try {
                $roleObject = $this->getRole($role);
            } catch (Zend_Acl_Role_Registry_Exception $exc) {
                //if role is not registered because it is the role of a differnt module
                continue;
            }
            $res = $this->_getRules($this->get('frontend'), $roleObject);
            if(!empty($res)) {
                $result = array_merge($result, array_keys($res['byPrivilegeId']));
            }
        }
        return array_values(array_unique($result));
    }
}
