<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

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
     * Singleton Instanzen
     *
     * @var Zend_Config enthält die acl-Config aller Module bereits gemergt
     */
    public $_aclConfigObject = null;

    /**
     * Singleton Instanz - Hole Acl-Instanz
     *
     * - prüft, ob bereits eine Instanz erstellt wurde;
     *   falls ja, wird diese zurückgegeben
     *
     * @return ZfExtended_Acl
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
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
     * Erstellt ACL mit Rollen, Ressourcen und Rechten
     * - erwartet bei mindestens einem Modul im Unterverzeichnis configs eine aclConfig.ini
     * - Deklaration als protected verhindert, dass mehrere Acl-Objekte erstellt werden können,
     *   da Acl nur mittels der statischen Methode getInstance erstellt und in Zend_Registry abgelegt werden kann
     * - Es werden nur "allow"-Regeln gesetzt, da alle Privilegien auf eine Resource,
     *   die nicht explizit erlaubt sind durch Zend_Acl denied werden.
     */
    protected function __construct() {
        $this->_aclConfigObject = $this->getAclConfig();
        
        $this->addRoles();
        
        $this->addResources();

        $this->addRules();
    }
    
    /**
     * erstellt aus der acl_config.ini des aktuellen Moduls ein Zend_Config-Objekt
     *
     * - erlaubt Überschreibung durch /iniOverwrites/'.APPLICATION_AGENCY.'/'.
                Zend_Registry::get('module').'AclConfig.ini'
     *
     * @return Zend_Config_Ini
     */
    public function getAclConfig(){
        $overwriteFile = APPLICATION_PATH .'/iniOverwrites/'.APPLICATION_AGENCY.'/'.
                Zend_Registry::get('module').'AclConfig.ini';
        $file = APPLICATION_PATH.'/modules/'.Zend_Registry::get('module').'/configs/aclConfig.ini';
        if(file_exists($overwriteFile)){
            $path = $overwriteFile;
        }
        elseif(file_exists($file)){
            $path = $file;
        }
        else{
            throw new Zend_Exception('Keine aclConfig.ini gefunden', 0);
        }
        return new Zend_Config_Ini($path,NULL,$options = array( 'allowModifications' => true));;
    }

    protected function addRules(){
       foreach ($this->_aclConfigObject->rules as $role => $rule) {
            foreach ($rule as $resource => $rule2) {
                if ('all' == $rule2) {
                    if($resource == 'frontend' && !$this->isFrontendRight()){
                        throw new Zend_Exception('For the resource "frontend" no rights are registered');
                    }
                    $this->allow($role, $resource);
                    continue;
                }
                foreach ($rule2 as $privilege) {
                    if($this->allowFrontendPrivilege($role,$resource,$privilege)){
                        continue;
                    }
                    if($this->allowControllerPrivilege($role,$resource,$privilege)){
                        continue;
                    }
                    $this->allowOtherPrivilege($role,$resource,$privilege);
                }
            }
        }
    }
    
    protected function addRoles(){
       foreach ($this->_aclConfigObject->roles as $role) {
            $this->addRole(new Zend_Acl_Role($role));
        }
    }
    
    protected function addResources(){
       $controllers = $this->getControllers();
       foreach ($controllers as $resource) {
            $this->add(new Zend_Acl_Resource($resource));
        }
        foreach ($this->_aclConfigObject->resources as $resource) {
            $this->add(new Zend_Acl_Resource($resource));
        }
    }
    /**
     * checks, if $privilege is other Privilege and if yes allows it
     *
     * @param string $role
     * @param string $resource
     * @param string $privilege
     * @throws Zend_Exception if 
     */
    protected function allowOtherPrivilege(string $role,string $resource,string $privilege){
        //versuche, Klasse über Autoloader zu laden
        try {
            new $resource;
            if(method_exists($resource.'Controller', $privilege.'Action')
                    or method_exists($resource, $privilege)){
                $this->allow($role, $resource, $privilege);
            }
            else{
                throw new Zend_Exception();
            }
        }
        catch (Exception $exc) {
            throw new Zend_Exception('Das in aclConfig.ini genannte Privileg '.
                    $privilege.' entspricht keiner Methode '.$privilege.
                    'Action or '.$privilege.' der Resource '.$resource , 0 );
        }
    }
    /**
     * checks, if $privilege is ControllerPrivilege and if yes allows it
     *
     * @param string $role
     * @param string $resource
     * @param string $privilege
     * @return boolean true if $privilege is ControllerPrivilege, false if not
     */
    protected function allowControllerPrivilege(string $role,string $resource,string $privilege){
        //prüfe, ob Action überhaupt existiert - das tut Zend_Acl leider nicht von sich aus
        try {
            if(method_exists($resource.'Controller', $privilege.'Action')){
                $this->allow($role, $resource, $privilege);
                return true;
            }
            return false;
        }
        catch (Exception $exc) {
            return false;
        }
    }
    /**
     * checks, if $privilege is frontendRight and if yes allows it if applicable
     *
     * @param string $role
     * @param string $resource
     * @param string $privilege
     * @return boolean true if resource is frontend, false if not
     * @throws Zend_Exception if resource is frontend, but $privilege is not defined as frontendRight
     */
    protected function allowFrontendPrivilege(string $role,string $resource,string $privilege){
        if($resource != 'frontend'){
            return false;
        }
        $this->allow($role, $resource, $privilege);
        return true;
    }
    /**
     * check, if given frontendright exists (is listed in [frontendRights] in aclConfig.ini
     *
     * @param string $right, default = all; if all is given here, the method checks,
     *      if there is at least one frontendRight defined
     * @return boolean
     */
    public function isFrontendRight($right = 'all'){
        if(!isset($this->_aclConfigObject->frontendRights)){
            return false;
        }
        $rights = $this->_aclConfigObject->frontendRights->toArray();
        
        return count($rights) > 0 && ($right === 'all' || in_array($right, $rights));
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
        $path = $controllerDirs[$module];
        foreach (scandir($path) as $file) {
            if (strstr($file, "Controller.php") !== false) {
                include_once $path . DIRECTORY_SEPARATOR . $file;
            }
        }
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'Zend_Controller_Action')and preg_match("'Controller$'",$class)) {
                $controller = strtolower(substr($class, 0, strpos($class, "Controller")));
                $controllers[] = $controller;
            }
        }
        return $controllers;
    }
    
    /**
     * Returns a list of frontend privileges / rights to the given roles
     * @param array $roles
     */
    public function getFrontendRights(array $roles) {
        $result = array();
        foreach($roles as $role) {
            $res = $this->_getRules($this->get('frontend'), $this->getRole($role));
            if(!empty($res)) {
                $result = array_merge($result, array_keys($res['byPrivilegeId']));
            }
        }
        return array_unique($result);
    }
}
