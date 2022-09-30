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

/**
 * Searches for database alter files to be imported
 */
class ZfExtended_Models_Installer_DbFileFinder {

    const FILE_META = 'metainformation.xml';
    const DEFAULT_ORIGIN = 'application';


    /**
     * contains a mapping between requested file, and file to be read instead.
     * Used for the overwriting mechanism of sql files.
     * @var array
     */
    protected array $replacements = [];

    /**
     * Contains a flat array of dependencies
     * Dependency means, that the key (containing an absolute path) has to wait for the value (also absolute path) to be imported
     * @var array 
     */
    protected array $pathDependencies = [];
    
    /**
     * The sql files to import, already ordered
     * @var array
     */
    protected array $toImport = [];

    /**
     * returns all available SQL files, already in the order to be imported
     * @param array $additionalPathes
     * @return array
     * @throws Zend_Exception
     * @throws ZfExtended_Exception
     * @throws ZfExtended_NoAccessException
     * @throws Exception
     */
    public function getSqlFilesOrdered(array $additionalPathes): array
    {
        $this->addCoreSqlFiles();
        $this->addPluginsSearchPathList();
        $this->addAdditionalSqlFiles($additionalPathes);
        $this->mergeReplacements();
        // create flat array and respect dependencies
        $files = $this->flatten();

        if(count($this->pathDependencies) > 0){

            $debug = false; // only to test the reordering-algorithm
            if($debug){ error_log('FOUND DEPENDENCIES: '.print_r($this->pathDependencies, 1)); }

            // copy dependencies to keep original data
            $dependencies = $this->pathDependencies;

            // first remove dependencies that cannot be resolved because they are either not in the set of files or the files are depend on are not in the set
            $allPathes = [];
            $dependenciesToRemove = [];
            foreach($files as $fileData){
                $allPathes[$fileData['absolutePath']] = 1;
            }
            foreach($dependencies as $dependentPath => $dependsOn){
                if(array_key_exists($dependentPath, $allPathes)){
                    $dependsOnNew = [];
                    foreach($dependsOn as $path){
                        if(array_key_exists($path, $allPathes)){
                            $dependsOnNew[] = $path;
                        }
                    }
                    if(count($dependsOnNew) > 0){
                        $dependencies[$dependentPath] = $dependsOnNew;
                    } else {
                        if($debug){ error_log('DEPENDENCY: '.$dependentPath.' HAS NOTHING IT DEPENDS ON IN THE FILES TO PROCESS'); }
                        // remove dependencies without counterparts
                        $dependenciesToRemove[] = $dependentPath;
                    }
                } else {
                    // remove dependencies that are not in the pathes to process
                    $dependenciesToRemove[] = $dependentPath;
                    if($debug){ error_log('DEPENDENCY: '.$dependentPath.' NOT PART OF THE FILES TO PROCESS'); }
                }
            }
            foreach($dependenciesToRemove as $removePath){
                unset($dependencies[$removePath]);
            }

            // second reorder to respect dependencies
            if(count($dependencies) > 0){
                $reorderedFiles = [];
                $delayedFiles = [];
                $currentOrigin = null;
                $lastPath = null;
                foreach($files as $fileData){
                    $path = $fileData['absolutePath'];
                    $origin = $fileData['origin'];
                    $processed = false;
                    if($currentOrigin === $origin){
                        if($debug){ error_log('DELAYED FOLLOW-UP DEPENDENCY WITH ORIGIN '.$origin.': '.$path); }
                        // these are follow-ups to the last delayed file with the same origin, we have to add them to the dependencies list (we do not expect internal dependencies in a origin to be set)
                        if(!array_key_exists($path, $dependencies)){
                            $dependencies[$path] = [$lastPath];
                        } else if(!in_array($lastPath, $dependencies[$path])){
                            // to make sure the order within a delayed postponed origin-file does not change, we add origin-internal dependencies pointing to the file before
                            $dependencies[$path][] = $lastPath;
                        }
                        $delayedFiles[$path] = $fileData;
                    } else if(array_key_exists($path, $dependencies)){
                        if($debug){ error_log('FOUND DEPENDENCY: '.$path); }
                        // file has dependencies
                        if(count($dependencies[$path]) === 0){
                            // dependencies already processed
                            unset($dependencies[$path]);
                            $reorderedFiles[] = $fileData;
                            $processed = true;
                            $currentOrigin = null;
                            if($debug){ error_log('ADDED ALREADY PROCESSED DEPENDENCY: '.$path); }
                        } else {
                            // we need to delay the file
                            $delayedFiles[$path] = $fileData;
                            $currentOrigin = $fileData['origin'];
                            if($debug){ error_log('DELAYED PATH: '.$path); }
                        }
                    } else {
                        $reorderedFiles[] = $fileData;
                        $processed = true;
                        $currentOrigin = null;
                    }
                    // we need to reduce the dependencies if a file was processed
                    if($processed){
                        $pathesToRemove = [];
                        // search all dependencies for the processed path
                        foreach($dependencies as $dependentPath => $dependsOn){
                            // if a dependancy is depending on the processed path we need to remove this dependency. If this leads to no dependencies left, we add this dependency if it already was delayed and remove it alltogether
                            // we also have to remove pathes, we processed this way, incrementally. This relies on the logic, that the order of delayed sections of origins is still intact
                            $this->removeItemFromArray($dependsOn, $path);
                            foreach($pathesToRemove as $removePath){
                                $this->removeItemFromArray($dependsOn, $removePath);
                            }
                            if(count($dependsOn) === 0 && array_key_exists($dependentPath, $delayedFiles)){
                                $reorderedFiles[] = $delayedFiles[$dependentPath];
                                $pathesToRemove[] = $dependentPath;
                                if($debug){ error_log('ADDED DELAYED DEPENDENCY: '.$dependentPath); }
                            } else {
                                $dependencies[$dependentPath] = $dependsOn;
                            }
                        }
                        foreach($pathesToRemove as $removePath){
                            unset($dependencies[$removePath]);
                            unset($delayedFiles[$removePath]);
                        }
                    }
                    $lastPath = $path;
                }
                // check for still delayed files, they can be added now
                foreach($delayedFiles as $path => $fileData){
                    if(array_key_exists($path, $dependencies) && count($dependencies[$path]) > 0){
                        throw new ZfExtended_Exception('DbFileFinder::getSqlFilesOrdered: added path "'.$path.'" with unresolved dependencies ["'.implode('", "', $dependencies[$path]).'"]');
                    }
                    $reorderedFiles[] = $fileData;
                }
                $files = $reorderedFiles;
            }
        }
        return $files;
    }

    /**
     * Removes the passed item from the array if it exists. The array is passed as reference, no need for return
     * @param array $array
     * @param string $item
     */
    private function removeItemFromArray(array &$array, string $item){
        $index = array_search($item, $array);
        if($index !== false){
            array_splice($array, $index, 1);
        }
    }

    /**
     * @throws ZfExtended_Exception
     * @throws Zend_Exception
     * @throws Exception
     */
    protected function addCoreSqlFiles(): void
    {
        $searchPaths = $this->getSearchPathList();
        foreach($searchPaths as $path) {
            $this->addFilesFromPath($path);
        }
    }

    /**
     * Adds the path to the alter-files needed for tests to the path
     * @param array $paths
     * @return void
     * @throws Exception
     */
    protected function addAdditionalSqlFiles(array $paths): void
    {
        foreach($paths as $path) {
            $this->addFilesFromPath($path);
        }
    }

    /**
     * @throws Exception
     */
    private function addFilesFromPath(string $path): void
    {
        $name = $this->parseMetaInformation($path);
        $this->iterateThroughDirectory($path, $name);
        //sort the loaded files by name, this is the initial natural order
        ksort($this->toImport[$name]);
    }

    /**
     * merges the arrays in toImport together to one array
     */
    protected function flatten(): array
    {
        $result = [];
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
    protected function iterateThroughDirectory(string $path, string $name): void
    {
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
            if(str_starts_with($filename, 'deinstall_')){
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
    protected function handleReplacement(string $name, string $targetPackage, string $pathname): void
    {
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
     * returns true if given file ends to case-insensitive ".sql" or ".php"
     * @param SplFileInfo $file
     * @return boolean
     */
    protected function isFileToProcess(SplFileInfo $file): bool
    {
        $suffix = strtolower(substr($file->getFilename(), -4));
        return $suffix === '.sql' || $suffix === '.php';
    }
    
    /**
     * merges the data of the replaced SQL files at the correct place into the final tree
     */
    protected function mergeReplacements(): void
    {
        foreach($this->replacements as $name => $files) {
            foreach($files as $filename => $data) {
                if(isset($this->toImport[$name]) && isset($this->toImport[$name][$filename])) {
                    $this->toImport[$name][$filename] = $data;
                }
            }
        }
    }

    /**
     * Parses the meta-information and the name of the sql package, evaluating the name and adding path dependencies.
     * Returns the name, default is "application" if the meta-data does not contain any name information or no meta-file exists or the meta-file is invalid
     *
     * @param string $path
     * @return string
     */
    protected function parseMetaInformation(string $path): string
    {
        $metaFile = $path.self::FILE_META;
        if(!file_exists($metaFile) || !is_readable($metaFile)) {
            return self::DEFAULT_ORIGIN;
        }
        try {
            $iterator = new SimpleXMLIterator($metaFile, 0, true);
            $data = $this->iteratorToArray($iterator);
            if(array_key_exists('file', $data)){
                foreach($data['file'] as $file){
                    if(array_key_exists('name', $file) && !empty($file['name']) && array_key_exists('dependency', $file) && !empty($file['dependency'])){
                        $filePath = $path.$file['name'][0];
                        $this->pathDependencies[$filePath] = [];
                        foreach($file['dependency'] as $dependency){
                            $dependency = ltrim($dependency, '/');
                            // if the dependency is given with the application-dir (what should be the case), we need to remove that
                            if(str_starts_with($dependency, 'application/')){
                                $dependency = substr($dependency, 12);
                            }
                            $this->pathDependencies[$filePath][] = APPLICATION_PATH.'/'.ltrim($dependency, '/');
                        }
                    }
                }
            }
            return (array_key_exists('name', $data) && !empty($data['name'])) ? $data['name'][0] : self::DEFAULT_ORIGIN;
        } catch(Throwable) {
            return self::DEFAULT_ORIGIN;
        }
    }

    /**
     * Helper to parse metainformation.xml files.
     * Ugly, we should better have taken JSON, it is so much easier to handle...
     * @param SimpleXMLIterator $sxi
     * @return array
     */
    private function iteratorToArray(SimpleXMLIterator $sxi): array {
        $a = array();
        for( $sxi->rewind(); $sxi->valid(); $sxi->next() ) {
            if(!array_key_exists($sxi->key(), $a)){
                $a[$sxi->key()] = array();
            }
            if($sxi->hasChildren()){
                $a[$sxi->key()][] = $this->iteratorToArray($sxi->current());
            } else{
                $a[$sxi->key()][] = strval($sxi->current());
            }
        }
        return $a;
    }

    /**
     * returns a list of paths where should be looked for sql files
     * @return array [string]
     * @throws Zend_Exception
     * @throws ZfExtended_Exception
     */
    public function getSearchPathList(): array
    {
        $config = Zend_Registry::get('config');
        $res = $config->sqlPaths;
        if(empty($res)) {
            throw new ZfExtended_Exception('No SQL search paths found in $config->sqlPaths!');
        }
        return array_merge($res->toArray());
    }

    /**
     * adds the database files of the plug-ins of the enabled modules
     * @throws ZfExtended_NoAccessException
     */
    protected function addPluginsSearchPathList(): void
    {
        $pluginManager = new ZfExtended_Plugin_Manager();
        $plugins = $pluginManager->getAvailable();
        foreach($plugins as $pluginName => $pluginCls) {
            try {
                $ref = new ReflectionClass($pluginCls);
            } catch (ReflectionException) {
                continue;
            }
            $singlePluginDbPath = dirname($ref->getFileName()).'/database';
            if(is_dir($singlePluginDbPath) && $this->checkUnistallSQLfiles($singlePluginDbPath)){
                $this->iterateThroughDirectory($singlePluginDbPath, $pluginName);
                //if the database dir exists, but is empty, then nothing can be sorted
                if(empty($this->toImport[$pluginName])) {
                    continue;
                }
                //sort the loaded files by name, this is the initial natural order
                ksort($this->toImport[$pluginName]);
            }
        }
    }

    /**
     * checks, if every sql-file of a plugin has a de-install sql-file
     * @param string $pluginDatabaseDir
     * @return boolean
     * @throws ZfExtended_NoAccessException
     */
    protected function checkUnistallSQLfiles(string $pluginDatabaseDir): bool
    {
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
            if(str_starts_with($filename, 'deinstall_')){
                continue;
            }
            $deinstallFileName = $file->getPath().'/deinstall_'.$file->getBasename();
            if(!file_exists($deinstallFileName)){
                error_log('Plugin-Installation: The file '.$deinstallFileName.' does not exist. Plugin-SQL can not be installed.');
                $r = false;
            }
        }
        return $r;
    }
}
