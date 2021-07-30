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
/**
 */
class ZfExtended_Models_Installer_DbFileFinder {
    const FILE_META = 'metainformation.xml';
    
    /**
     * contains a mapping between requested file, and file to be read instead.
     * Used for the overwritting mechanism of sql files.
     * @var array
     */
    protected $replacements = array();
    
    /**
     * The sql files to import, already ordered
     * @var array
     */
    protected $toImport = array();

    /**
     * returns all available SQL files, already in the order to be imported
     */
    public function getSqlFilesOrdered() {
        $this->addCoreSqlFiles();
        $this->addPluginsSearchPathList();
        $this->mergeReplacements();
        //@todo implement here the additionaly resort by dependencies, which are read out from meta file!
        return $this->flatten();
    }
    
    protected function addCoreSqlFiles() {
        $searchPaths = $this->getSearchPathList();
        foreach($searchPaths as $path) {
            $meta = $this->loadMetaInformation($path);
            $name = $this->getSqlPackageName($meta);
        
            $this->iterateThroughDirectory($path, $name);
            //sort the loaded files by name, this is the initial natural order
            ksort($this->toImport[$name]);
        }
    }
    
    /**
     * merges the arrays in toImport together to one array
     */
    protected function flatten() {
        $result = array();
        foreach($this->toImport as $todo) {
            $result = array_merge($result, array_values($todo));
        }
        return $result;
    }
    
    /**
     * iterates through the contents of the given directory
     * @param string $path
     * @param string $name
     */
    protected function iterateThroughDirectory($path, $name) {
        foreach (new DirectoryIterator($path) as $fileInfo) {
            $filename = $fileInfo->getFilename();
            if($fileInfo->isDot() || $fileInfo->isFile() && !$this->isFileToProcess($fileInfo)){
                continue;
            }
            //if the found file is a directory, it may contain overwrites for the db origin with the given name
            if($fileInfo->isDir()) {
                $this->handleReplacement($name, $filename, $fileInfo->getPathname());
                continue;
            }
            if(preg_match('"^deinstall_"', $filename)){
                continue;
            }
            if(!isset($this->toImport[$name])) {
                $this->toImport[$name] = array();
            }
            $this->toImport[$name][$filename] = array(
                'absolutePath' => $fileInfo->getPathname(),
                'relativeToOrigin' => $filename,
                'origin' => $name,
            );
        }
    }
    
    /**
     * files in the given directory are replacing the same named files in the targetPackage
     * @param string $name
     * @param string $targetPackage
     * @param string $pathname
     */
    protected function handleReplacement($name, $targetPackage, $pathname) {
        if(!isset($this->replacements[$targetPackage])) {
            $this->replacements[$targetPackage] = array();
        }
        foreach(new DirectoryIterator($pathname) as $overwrite) {
            if(!$overwrite->isFile() || !$this->isFileToProcess($overwrite)) {
                continue;
            }
            $this->replacements[$targetPackage][$overwrite->getFilename()] = array(
                'absolutePath' => $overwrite->getPathname(),
                'relativeToOrigin' => $targetPackage.'/'.$overwrite->getFilename(),
                'origin' => $name,
            );
        }
    }
    
    /**
     * returns true if given file ends to case insensitive ".sql" or ".php"
     * @param SplFileInfo $file
     * @return boolean
     */
    protected function isFileToProcess(SplFileInfo $file) {
        $suffix = strtolower(substr($file->getFilename(), -4));
        return $suffix === '.sql' || $suffix === '.php';
    }
    
    /**
     * merges the data of the replaced SQL files at the correct place into the final tree
     */
    protected function mergeReplacements() {
        foreach($this->replacements as $name => $files) {
            foreach($files as $filename => $data) {
                if(isset($this->toImport[$name]) && isset($this->toImport[$name][$filename])) {
                    $this->toImport[$name][$filename] = $data;
                }
            }
        }
    }
    
    /**
     * returns the name of the sql package for the given meta informations
     * default is "application" if the meta data does not contain any name information
     *
     * @param string $meta
     * @return string
     */
    protected function getSqlPackageName($meta) {
        if(is_null($meta)) {
            return 'application';
        }
        return (string)$meta->name;
    }
    
    /**
     * loads and returns all meta information to this database source directory or null if nothing exists.
     * @param string $path
     * @return stdClass|null
     */
    protected function loadMetaInformation($path) {
        $file = $path.self::FILE_META;
        if(!file_exists($file) || !is_readable($file)) {
            return null;
        }
        return new SimpleXMLIterator($file, 0, true);
    }
    
    /**
     * returns a list of paths where should be looked for sql files
     * @return [string]
     */
    protected function getSearchPathList() {
        $config = Zend_Registry::get('config');
        $res = $config->sqlPaths;
        if(empty($res)) {
            throw new ZfExtended_Exception('No SQL search paths found in $config->sqlPaths!');
        }
        return array_merge($res->toArray());
    }
    
    /**
     * returns a list of plugin-paths where should be looked for sql files
     * @return array ('PluginName'=>'pathToPluginDatabaseDir',...)
     */
    
    protected function addPluginsSearchPathList() {
        $moduleDirs = new DirectoryIterator(APPLICATION_PATH.'/modules/');
        foreach ($moduleDirs as $moduleDirInfo) {
            if ($moduleDirInfo->isDot() || !$moduleDirInfo->isDir()) {
                continue;
            }
            //get plugins of this module
            $pluginDirPath = $moduleDirInfo->getPathname().'/Plugins';
            if(!\is_dir($pluginDirPath)){
                continue;
            }
            $pluginDirs = new DirectoryIterator($pluginDirPath);
            foreach ($pluginDirs as $pluginDirInfo) {
                if ($pluginDirInfo->isDot() || !$pluginDirInfo->isDir()) {
                    continue;
                }
                $singlePluginDbPath = $pluginDirInfo->getPathname().'/database';
                if(is_dir($singlePluginDbPath) && $this->checkUnistallSQLfiles($singlePluginDbPath)){
                    $name = $pluginDirInfo->getBasename();
                    $this->iterateThroughDirectory($singlePluginDbPath, $name);
                    //if the database dir exists, but is empty, then nothing can be sorted
                    if(empty($this->toImport[$name])) {
                        continue;
                    }
                    //sort the loaded files by name, this is the initial natural order
                    ksort($this->toImport[$name]);
                }
            }
        }
    }
    /**
     * //checks, if every sql-file of a plugin has an uninstall sql-file
     *
     * @return boolean
     */
    protected function checkUnistallSQLfiles(string $pluginDatabaseDir) {
        $r = true;
        $files = new DirectoryIterator($pluginDatabaseDir);
        foreach ($files as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }
            $filename = $file->getBasename();
            if(!$file->isReadable()){
                throw new ZfExtended_NoAccessException('The file '.$file->getFilename().' is not readable.');
            }
            if(preg_match('"^deinstall_"', $filename)){
                continue;
            }
            $deinstallFileName = $file->getPath().'/deinstall_'.$file->getBasename();
            if(!\file_exists($deinstallFileName)){
                error_log('Plugin-Installation: The file '.$deinstallFileName.' does not exist. Plugin-SQL can not be installed.');
                $r = false;
            }
        }
        return $r;
    }
}
