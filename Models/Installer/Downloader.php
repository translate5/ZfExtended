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

/**
 * @package ZfExtended
 * @version 2.0
 */
class ZfExtended_Models_Installer_Downloader {
    const DEPENDENCIES_FILE = '/application/config/dependencies.json';
    const DEPENDENCIES_INSTALLED_FILE = '/application/config/dependencies-installed.json';
    
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
        $neededDependencies = $applicationRoot.self::DEPENDENCIES_FILE;
        $installedDependencies = $applicationRoot.self::DEPENDENCIES_INSTALLED_FILE;
        $this->dependencies = new ZfExtended_Models_Installer_Dependencies($neededDependencies, $installedDependencies);
    }
    
    /**
     * This method checks if the application is uptodate by package zip hash.
     * It assumes that the application is already listed in deps-installed.json,
     * if not or if no dep file is given it is also marked as uptodate!
     * This is needed for legacy applications which are not using the install-and-update script
     * and have therefore not deps-installed.json file.
     * The result is, that this method cannot be used in installation process,
     * which would also be nonsense.
     *
     * @return boolean
     */
    public function applicationIsUptodate(){
        $needed = $this->dependencies->getNeeded();
        if(empty($needed)) {
            return true;
        }
        $app = $needed->application;
        $this->fetchHashTable();
        $installed = $this->dependencies->getInstalled($app->name);
        return is_null($installed) || $this->isUpToDate($app);
    }
    
    /**
     * Returns the application version which is available in the update channel
     */
    public function getAvailableVersion(): ?string {
        $url = $this->dependencies->getNeeded()->versionfile;
        if(empty($url)) {
            return null;
        }
        $versionContent = @file_get_contents($url);
        if(empty($versionContent)) {
            return null;
        }
        return ZfExtended_Utils::getAppVersion($versionContent);
    }

    /**
     * pulls the application from server and returns dependencies where the license has to be accepted
     * @param string $zipOverride an own application zip can be provided for manual overrides
     * @return array
     */
    public function pullApplication($zipOverride = null) {
        $this->fetchHashTable();
        $this->updateApplication($zipOverride);
        $deps = $this->dependencies->getNeeded()->dependencies;
        $dependenciesToAccept = array();
        foreach($deps as $dependency) {
            if($this->isUpToDate($dependency)) {
                continue;
            }
            $dependenciesToAccept[] = $dependency;
        }
        return $dependenciesToAccept;
    }
    
    public function pullDependencies($cleanBefore = false) {
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
     * @param string $zipOverride an own application zip can be provided for manual overrides
     */
    protected function updateApplication($zipOverride = null) {
        $app = $this->dependencies->getNeeded()->application;
        $installed = $this->dependencies->getInstalled($app->name);
        $isZipOverride = !empty($zipOverride);
        if(!$isZipOverride && !empty($installed) && $installed->md5 === $this->getLiveHash($app)) {
            $this->log('Application '.$app->name.' is up to date!');
            return;
        }
        if(!$isZipOverride && !$this->fetch($app)) {
            $this->log('Could not fetch application package '.$app->name);
            return;
        }
        
        if($isZipOverride) {
            $app->targetFile = $zipOverride;
        }
        
        if($this->install($app, false, true)) {
            $this->dependencies->markInstalled($app);
            $msg = $app->name;
            if($isZipOverride) {
                $msg .= ' from overriden zip '.$zipOverride;
            }
            $this->log('Updated application '.$msg);
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
        //we have to get the live hash of the new dependency here, not the already installed one.
        // on updates mostly the URL changes because of changed URL, so the hash for the new URL must be checked
        $liveMatched = $this->getLiveHash($dependency) === $installed->md5;
        $depMatched = empty($dependency->md5) || $installed->md5 === $dependency->md5;
        return $liveMatched && $depMatched;
    }
    
    /**
     * @param stdClass $dependency
     * @return NULL|multitype:
     */
    protected function getLiveHash(stdClass $dependency) {
        $parsed = (object) $dependency->url_parsed;
        $idx = basename($parsed->path);
        if(empty($this->md5HashTable[$idx])){
            return null;
        }
        return $this->md5HashTable[$idx];
    }
    
    protected function fetchHashTable() {
        $hashTableDep = new stdClass();
        $url = $this->dependencies->getNeeded()->md5hashtable;
        $hashTable = @file_get_contents($url);
        if(empty($hashTable)) {
            //add error info to url if file_get_contents was failing
            if($hashTable === false) {
                $url .= ' with error '.error_get_last()['message'];
            }
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
     * @param bool $cleanBefore
     * @return boolean
     */
    protected function fetch(stdClass $dependency, $cleanBefore = false) {
        if(!$this->checkMemoryLimit()) {
            $this->log('Not enough memory available to process downloaded file. At least 512M needed in ini setting memory_limit! The value could not be set automatically by the uploader!');
            return false;
        }
        
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
        
        $liveMd5 = $this->getLiveHash($dependency);
        if(file_exists($target) && md5_file($target) === $liveMd5) {
            if(empty($dependency->md5)) {
                $dependency->md5 = $liveMd5;
            }
            $this->log('Package already fetched for package '.$dependency->name.' URL:'.$dependency->url);
            return true;
        }
        
        $msg  = 'Downloading '.$dependency->label;
        if(!empty($dependency->version)) {
            $msg .= ' ('.$dependency->version.')';
        }
        $msg .= ' from '.$dependency->url;
        $this->log($msg);
        $data = file_get_contents($dependency->url);
        if(empty($data)) {
            $this->log('Fetched package for '.$dependency->name.' was empty! URL:'.$dependency->url);
            return false;
        }
        
        $targetMd5 = md5($data);
        if(empty($dependency->md5)) {
            $dependency->md5 = $targetMd5;
        }
        if($targetMd5 !== $liveMd5) {
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
    
    /**
     * Checks for at leaset 512 MB memory limit, since our downloaded files are shortly kept in memory.
     * @return boolean
     */
    protected function checkMemoryLimit() {
        $bytes = function ($string) {
            $last = strtolower(substr(trim($string), -1, 1));
            $string = substr(trim($string), 0, -1);
            switch($last) {
                case 'g':
                    $string *= 1024;
                case 'm':
                    $string *= 1024;
                case 'k':
                    $string *= 1024;
            }
            return $string;
        };

        if($bytes(ini_get('memory_limit')) >= 512 * 1024 * 1024) {
            return true;
        }
        if(ini_set('memory_limit', '512M') === false) {
            //could not set mem limit to 512 MB
            return false;
        }
        //could increase mem limit
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
     * @param bool $cleanBefore
     * @param bool $overwrite
     * @return boolean
     */
    protected function install(stdClass $dependency, $cleanBefore = false, $overwrite = false) {
        $zip = new ZipArchive;

        //cleaning is only allowed with target given!
        if($cleanBefore && empty($dependency->target)) {
            //nothing to extract since no target is given
            // for example if the downloaded file is no zip file
            return true;
        }

        $targetDir = $this->applicationRoot.'/'.$dependency->target;
        if(!empty($dependency->preventTargetCleaning)) {
            $overwrite = true;
        }

        if(!$overwrite && file_exists($targetDir)) {
            if(!$cleanBefore) {
                $this->log('Could not unzip target directory for dependency package '.$dependency->name.' already exists! ZipFile:'.$dependency->targetFile);
                return false;
            }
            self::removeRecursive($targetDir);
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
    
    public static function removeRecursive($toRemove) {
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
