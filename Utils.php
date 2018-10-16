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
 * Defines some global functions.
 * Functions should be defines as public static function, so they can be calles as:
 * 
 * ZfExtended_Utils::function()
 * 
 * from everywhere in the application.
 * 
 */
class ZfExtended_Utils {
    
    /**
     * Get an array of all class-constants-names from class $className which begin with $prefix.
     *
     * @param string $className
     * @param string $praefix
     *
     * @return array of constants-names (key) and its values (value)
     */
    public static function getConstants(string $className, string $praefix) {
        $constants = array();
        
        $reflectionClass = new ReflectionClass($className);
        $classConstants = $reflectionClass->getConstants();
        
        foreach($classConstants as $key => $value) {
            if (strpos($key, $praefix) === 0) {
                $constants[$key] = $value;
            }
        }
        
        return $constants;
    }
    
    /**
     * does a recursive copy of the given directory
     * @param string $src Source Directory
     * @param string $dst Destination Directory
     */
    public static function recursiveCopy(string $src, string $dst) {
        $dir = opendir($src);
        if(!file_exists($dst)) {
            @mkdir($dst);
        }
        $SEP = DIRECTORY_SEPARATOR;
        while(false !== ( $file = readdir($dir)) ) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($src.$SEP.$file)) {
                self::recursiveCopy($src.$SEP.$file, $dst.$SEP.$file);
            }
            else {
                copy($src.$SEP.$file, $dst.$SEP.$file);
            }
        }
        closedir($dir);
    }
    
    /**
     * cleans the filenames in zip containers up 
     * @param SplFileInfo $zipFile
     * @param string $prefixToRemove must be a directory inside the zip!
     */
    public static function cleanZipPaths(SplFileInfo $zipFile, $prefixToRemove) {
        $removalLength = mb_strlen($prefixToRemove);
        $zip = new ZipArchive();
        $zip->open($zipFile);
        $i = 0;
        while (true) {
            $name = $zip->getNameIndex($i);
            if($name === false) {
                break;
            }
            if(mb_strpos($name, $prefixToRemove) !== 0) {
                $i++;
                continue;
            }
            $newFileName = mb_substr($name, $removalLength + 1);
            if(empty($newFileName)) {
                $zip->deleteIndex($i); //we remove the empty directory which is stripped via prefixToRemove
            }
            else {
                $zip->renameIndex($i, $newFileName); //remove also the next / or \
            }
            $i++;
        }
        $zip->close();
    }
    
    /**
     * encodes the given utf8 filepath to the configured runtimeOptions.fileSystemEncoding
     * @param string $path
     * @return string $path 
     * @see ZfExtended_Utils::filesystemEncode
     */
    public static function filesystemEncode (string $path) {
        $config = Zend_Registry::get('config');
        return iconv('UTF-8', $config->runtimeOptions->fileSystemEncoding, $path);
    }
    
    /**
     * decodes the given filepath in the configured runtimeOptions.fileSystemEncoding to utf8 
     * @param string $path
     * @return string $path 
     * @see ZfExtended_Utils::filesystemDecode
     */
    public static function filesystemDecode (string $path) {
        $config = Zend_Registry::get('config');
        return iconv($config->runtimeOptions->fileSystemEncoding, 'UTF-8', $path);
    }
    
    public static function getAppVersion() {
        $versionFile = APPLICATION_PATH.'/../version';
        $regex = '/MAJOR_VER=([0-9]+)\s*MINOR_VER=([0-9]+).*\s*BUILD=([0-9]+).*/';
        if(file_exists($versionFile) && $res = preg_match($regex, file_get_contents($versionFile), $matches)) {
            array_shift($matches);
            return join('.', $matches);
        }
        return 'development';
    }
    
    /**
     * splits the text up into HTML tags / entities on one side and plain text on the other side
     * The order in the array is important for the following wordBreakUp, since there are HTML tags and entities ignored.
     * Caution: The ignoring is done by the array index calculation there!
     * So make no array structure changing things between word and tag break up!
     *
     * @param string $text
     * @return array $text
     */
    public static  function tagBreakUp($tagRegex,$text){
        if(is_null($tagRegex)){
            throw new Zend_Exception('Regex zur Tagerkennung ist NULL');
        }
        return preg_split($tagRegex, $text, NULL,  PREG_SPLIT_DELIM_CAPTURE);
    }
    
    /**
     * Zerlegt die Wortteile des segment-Arrays anhand der Wortgrenzen in ein Array,
     * welches auch die Worttrenner als jeweils eigene Arrayelemente enthält
     *
     * - parst nur die geraden Arrayelemente, denn dies sind die Wortbestandteile
     *   (aber nur weil tagBreakUp davor aufgerufen wurde und sonst das array nicht in der Struktur verändert wurde)
     *
     * @param array $segment
     * @return array $segment
     */
    public static function wordBreakUp($segment){
        $config = Zend_Registry::get('config');
        $regexWordBreak = $config->runtimeOptions->editor->export->wordBreakUpRegex;
        
        //by adding the count($split) and the $i++ only the array entries containing text (no tags) are parsed
        //this implies that only tagBreakUp may be called before and
        // no other array structure manipulating method may be called between tagBreakUp and wordBreakUp!!!
        for ($i = 0; $i < count($segment); $i++) {
            $split = preg_split($regexWordBreak, $segment[$i], NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            array_splice($segment, $i, 1, $split);
            $i = $i + count($split);
        }
        return $segment;
    }
}
