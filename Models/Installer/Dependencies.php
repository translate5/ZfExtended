<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**
 * @package ZfExtended
 * @version 2.0
 */
class ZfExtended_Models_Installer_Dependencies {
    
    /**
     * @var array
     */
    protected $neededDependencies;
    
    /**
     * @var array
     */
    protected $installedDependencies;
    
    /**
     * @var array
     */
    protected $paths = array();
    
    /**
     * @var array
     */
    protected $channels = array();
    
    /**
     * A copy of $installedDependencies which will not be modified by the install / update process, keeped for reference
     * @var array
     */
    protected $oldInstalledDependencies = array();
    
    /**
     * @param string $neededDependencies
     * @param string $installedDependencies
     */
    public function __construct($neededDependencies, $installedDependencies) {
        $this->paths['needed'] = $neededDependencies;
        $this->paths['installed'] = $installedDependencies;
        $this->reloadNeeded();
        $installed = new SplFileInfo($installedDependencies);
        if($installed->isReadable()) {
            $this->installedDependencies = $this->loadAndParseDepConfig($installed);
            $this->oldInstalledDependencies = $this->installedDependencies;
        }else {
            $this->installedDependencies = new stdClass();
        }
    }
    
    /**
     * Reloading needed dependencies
     */
    public function reloadNeeded() {
        $this->neededDependencies = $this->loadAndParseDepConfig(new SplFileInfo($this->paths['needed']));
        $this->channels = (array) $this->neededDependencies->channels;
        array_map(array($this, 'prepareDepConfig'), $this->neededDependencies->dependencies);
        $this->neededDependencies->md5hashtable = $this->evaluateUrlChannel($this->neededDependencies->md5hashtable);
        $this->prepareDepConfig($this->neededDependencies->application);
    }
    
    public function getNeeded() {
        return $this->neededDependencies;
    }
    
    /**
     * returns the list of installed deps, or if given a name the named dep, if not installed returns null
     * @param string $name
     * @return NULL|stdClass|multitype:
     */
    public function getInstalled($name = null) {
        if(empty($name)){
            return $this->installedDependencies;
        }
        if(empty($this->installedDependencies->$name)) {
            return null;
        }
        return $this->installedDependencies->$name;
    }
    
    /**
     * @todo
     * Removes the unused dependencies
     */
    public function removeUnused() {
        //TODO not used yet!
        //idea is: 
        //1. reload Needed Deps, 
        //3. compare newly loaded needed deps and oldInstalledDep, 
        //4. delete not needed anymore deps
    }
    
    protected function loadAndParseDepConfig(SplFileInfo $filename) {
        if(!$filename->isReadable()) {
            throw new Exception('Dependency Config does not exist or is not readable: '.$filename);
        }
        $json = file_get_contents($filename);
        $config = json_decode($json);
        if(empty($config)) {
            $json_error = json_last_error_msg();
            throw new Exception('Dependency Config empty, or non valid JSON: '.$json_error);
        }
        if(!is_object($config)) {
            throw new Exception('Dependency Config root element is no object!');
        }
        return $config;
    }
    
    /**
     * prepares the DepConfig Object, returns the parsed URL array
     */
    protected function prepareDepConfig(stdClass $dependency) {
        settype($dependency->name, 'string');
        settype($dependency->url, 'string');
        settype($dependency->basename, 'string');
        settype($dependency->target, 'string');
        settype($dependency->md5, 'string');
        settype($dependency->symlink, 'array');
        settype($dependency->licenses, 'array');
        settype($dependency->version, 'string');
        
        $dependency->url = $this->evaluateUrlChannel($dependency->url);
        $dependency->url_parsed = parse_url($dependency->url);
    }
    
    /**
     * evaluates the channel prefix in a given URL to the configured URL prefix
     * @param string $url
     * @return string
     */
    protected function evaluateUrlChannel($url) {
        $search = array_map(function($i){
            return '^'.$i.':'; //add trailing ':' and leading '^'
        },array_keys($this->channels));
        
        return ltrim(str_replace($search, $this->channels, '^'.$url), '^');
    }
    
    /**
     * prepares the given dep to be stored as installed
     * @param stdClass $dependency
     */
    public function markInstalled(stdClass $dependency) {
        $name = $dependency->name;
        $this->installedDependencies->$name = $dependency;
    }
    
    /**
     * updates the installed dependencies file
     * @throws Exception
     */
    public function updateInstalled() {
        $installed = json_encode($this->installedDependencies);
        $res = file_put_contents($this->paths['installed'], $installed);
        if(empty($res)) {
            throw new Exception("Could not save installed dependencies file: ".$this->paths['installed']);
        }
    }
}