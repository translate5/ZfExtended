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
        $searchPaths = $this->getSearchPathList()->toArray();
        
        foreach($searchPaths as $path) {
            $meta = $this->loadMetaInformation($path);
            $name = $this->getSqlPackageName($meta);
        
            $this->iterateThroughDirectory($path, $name);
            //sort the loaded files by name, this is the initial natural order
            ksort($this->toImport[$name]);
        }
        
        $this->mergeReplacements();
        //@todo implement here the additionaly resort by dependencies, which are read out from meta file!
        return $this->flatten();
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
        return $res;
    }
}
