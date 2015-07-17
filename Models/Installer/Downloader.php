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
class ZfExtended_Models_Installer_Downloader {
    
    /**
     * @var ZfExtended_Models_Installer_Dependencies
     */
    protected $dependencies;
    
    /**
     * @var string
     */
    protected $applicationRoot = '';
    
    /**
     * @var array
     */
    protected $md5HashTable = array();
    
    public function __construct($applicationRoot) {
        $this->applicationRoot = $applicationRoot;
    }
    
    /**
     * @param string $neededDependencies
     * @param string $installedDependencies
     */
    public function initDependencies($neededDependencies, $installedDependencies) {
        $this->dependencies = new ZfExtended_Models_Installer_Dependencies($neededDependencies, $installedDependencies);
    }
    
    public function pull($cleanBefore = false) {
        $this->fetchHashTable();
        
        $this->updateApplication();
        
        $deps = $this->dependencies->getNeeded()->dependencies;
        foreach($deps as $dependency) {
            if($this->isUpToDate($dependency)) {
                $this->log('Package '.$dependency->name.' is up to date!');
                continue;
            }
            if(!$this->fetch($dependency, $cleanBefore)) {
                $this->log('Could not fetch dependency package '.$dependency->name);
                continue;
            }
            $this->log('Fetched dependency '.$dependency->name);
            if($this->install($dependency, $cleanBefore)) {
                $this->dependencies->markInstalled($dependency);
                $this->log('Installed dependency '.$dependency->name);
            }
        }
        $this->dependencies->updateInstalled();
        $this->dependencies->removeUnused();
    }
    
    /**
     * @param stdClass $deps
     */
    protected function updateApplication() {
        $app = $this->dependencies->getNeeded()->application;
        $md5 = $this->dependencies->getInstalled($app->name);
        if($md5 === $this->getLiveHash($app)) {
            $this->log('Application '.$app->name.' is up to date!');
            return;
        }
        if(!$this->fetch($app)) {
            $this->log('Could not fetch application package '.$app->name);
            return;
        }
        if($this->install($app, false, true)) {
            $this->dependencies->markInstalled($app);
            $this->log('Updated application '.$app->name);
            $this->dependencies->reloadNeeded();
        }
        else {
            $this->log('Could not update application '.$app->name);
        }
    }
    
    /**
     * @param stdClass $dependency
     * @return boolean
     */
    protected function isUpToDate(stdClass $dependency) {
        $installed = $this->dependencies->getInstalled($dependency->name);
        if(is_null($installed)) {
            return false;
        }
        $liveMatched = $this->getLiveHash($installed) === $installed->md5;
        $depMatched = !empty($dependency->md5) && $installed->md5 === $dependency->md5;
        return $liveMatched && $depMatched;
    }
    
    /**
     * @param stdClass $installed
     * @return NULL|multitype:
     */
    protected function getLiveHash(stdClass $installed) {
        $parsed = (object) $installed->url_parsed;
        $idx = basename($parsed->path);
        if(empty($this->md5HashTable[$idx])){
            return null;
        }
        return $this->md5HashTable[$idx];
    }
    
    protected function fetchHashTable() {
        $hashTableDep = new stdClass();
        $url = $this->dependencies->getNeeded()->md5hashtable;
        $hashTable = file_get_contents($url);
        if(empty($hashTable)) {
            throw new Exception('Could not fetch md5hashtable from URL:'.$url);
        }
        $this->md5HashTable = array();
        $rows = explode("\n", $hashTable);
        foreach($rows as $row) {
            preg_match('/^([\w]{32})[\s]+(.+)$/', $row, $matches);
            if(count($matches) != 3) {
                continue;
            }
            $this->md5HashTable[$matches[2]] = $matches[1];
        }
    }
    
    /**
     * fetches the dependency and unzips it locally
     * @param stdClass $dependency
     * @param boolean $cleanBefore
     * @return boolean
     */
    protected function fetch(stdClass $dependency, $cleanBefore = false) {
        $url = $dependency->url_parsed;
        if(!$url || empty($url['host']) || empty($url['path'])) {
            $this->log('Configured url for package '.$dependency->name.' is invalid! URL:'.$dependency->url);
            return false;
        }
        
        if(empty($dependency->basename)) {
            $dependency->basename = basename($url['path']);
        }
        $targetDir = new SplFileInfo($this->applicationRoot.'/downloads');
        $target = $dependency->targetFile = $targetDir.'/'.$dependency->basename;
        
        if(file_exists($target) && $cleanBefore){
            unlink($target);
        }
        
        if(file_exists($target) && md5_file($target) === $this->getLiveHash($dependency)) {
            $this->log('Package already fetched for package '.$dependency->name.' URL:'.$dependency->url);
            return true;
        }
        
        $this->log('Downloading '.$dependency->label.' from '.$dependency->url);
        $data = file_get_contents($dependency->url);
        if(empty($data)) {
            $this->log('Fetched package for '.$dependency->name.' was empty! URL:'.$dependency->url);
            return false;
        }
        
        $targetMd5 = md5($data);
        if(empty($dependency->md5)) {
            $dependency->md5 = $targetMd5;
        }
        if($targetMd5 !== $this->getLiveHash($dependency)) {
            $this->log('MD5 Hash for package '.$dependency->name.' does not match!');
            return false;
        }
        
        if(!$targetDir->isDir() && !mkdir($targetDir, 0770, true)) {
            $this->log('Cannot create target dir for dependency package '.$dependency->name.'! Dir:'.$targetDir);
            return false;
        }
        
        $res = file_put_contents($target, $data);
        if($res === false || $res === 0) {
            $this->log('Could not save downloaded package for dependency package '.$dependency->name.'! Target:'.$target);
            return false;
        }
        return true;
    }
    
    protected function getHttpPath(array $url) {
        settype($url['path'], 'string');
        settype($url['query'], 'string');
        settype($url['fragment'], 'string');
        return $url['path'].(empty($url['query'])?'':'?'.$url['query']).(empty($url['fragment'])?'':'#'.$url['fragment']);
    }
    
    /**
     * Install the package as defined in the dependency file
     * @param stdClass $dependency
     * @param boolean $cleanBefore
     * @param boolean $overwrite
     * @return boolean
     */
    protected function install(stdClass $dependency, $cleanBefore = false, $overwrite = false) {
        $zip = new ZipArchive;
        $targetDir = $this->applicationRoot.'/'.$dependency->target;
        
        if(!$overwrite && file_exists($targetDir)) {
            if(!$cleanBefore) {
                $this->log('Could not unzip target directory for dependency package '.$dependency->name.' already exists! ZipFile:'.$dependency->targetFile);
                return false;
            }
            else {
                $this->removeRecursive($targetDir);
            }
        }
        if (!$zip->open($dependency->targetFile)) {
            $this->log('Could not find downloaded zip file for dependency package '.$dependency->name.'! ZipFile:'.$dependency->targetFile);
            return false;
        }
        if(!$zip->extractTo($targetDir)){
            $this->log('Could not unzip downloaded package for dependency package '.$dependency->name.'! ZipFile:'.$dependency->targetFile);
            return false;
        }
        $zip->close();
        if(!empty($dependency->symlink)) {
            return $this->symlink($dependency);
        }
        return true;
    }
    
    protected function symlink(stdClass $dependency) {
        $symLink = $this->applicationRoot.'/'.$dependency->symlink[1];
        $f = new SplFileInfo($symLink);
        if($f->isLink()) {
            unlink($f);
        }
        if($f->isFile() || $f->isDir()) {
            $this->log('Symlink for dependency package '.$dependency->name.' already exists! Symlink:'.$symLink);
            return false;
        }
        $linksTo = $dependency->symlink[0];
        if(!symlink($linksTo, $symLink)){
            $this->log('Could not create package symlink for dependency package '.$dependency->name.'! Symlink:'.$symLink.' to '.$linksTo);
            return false;
        }
        return true;
    }
    
    protected function removeRecursive($toRemove) {
        $iterator = new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($toRemove, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $filename => $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($filename);
            } else {
                unlink($filename);
            }
        }
    }
    
    protected function log($msg) {
        echo 'Downloader: '.$msg."\n";
    }
}