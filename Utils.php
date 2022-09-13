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
 * Defines some global functions.
 * Functions should be defines as public static function, so they can be calles as:
 *
 * ZfExtended_Utils::function()
 *
 * from everywhere in the application.
 *
 */
class ZfExtended_Utils {

    const VERSION_DEVELOPMENT = 'development';


    /***
     * Convert the input date to mysql accepted date time format (Y-m-d H:i:s)
     * @param string $date
     * @return false|string
     */
    public static function toMysqlDateTime(string $date){
        $timestamp = strtotime($date);
        if(empty($timestamp)){
            return $date;
        }
        return date("Y-m-d H:i:s",$timestamp);
    }
    /**
     * returns an value / array of values found by the xpath similar path.
     * Currently supported only:
     * foo/bar/dada
     * to get from given root object:
     * $root->foo->bar->dada with isset checks, if is empty, return null.
     * if one of the fields is an array, each childnode is searched for the follwing path elements and the results are collected in an array
     *
     * here foo/bar/dada will return ["first","second"]
     * {
     *   "foo": [{
     *      "bar": {
     *          "dada": "first"
     *      }
     *   },{
     *      "bar": {
     *          "dada": "second"
     *      }
     *   }]
     * }
     *
     * @param mixed $root
     * @param string|array $path / separated string or array which contains the single elements
     */
    public static function xpath($root, $path) {
        if(empty($path)) {
            return $root;
        }
        if(is_string($path)) {
            $path = explode('/', $path);
        }
        if(empty($root)) {
            return null;
        }
        $current = array_shift($path);
        $matches = null;
        if(preg_match('/^([^\[]+)\[([0-9]+)\]$/', $current, $matches)) {
            //since here we assume an array as next step
            $current = $matches[1];
            array_unshift($path, $matches[2]);
        }
        if(is_object($root)) {
            if(property_exists($root, $current)) {
                return self::xpath($root->$current, $path);
            }
            //error_log($current.' # '.print_r($root,1));
            return null;
        }
        if(is_array($root)) {
            if(array_key_exists($current, $root)) {
                return self::xpath($root[$current], $path);
            }
            $result = [];
            foreach($root as $item) {
                $result[] = self::xpath($item, $path);
            }
            return $result;
        }
        return $root;
    }

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
     * Does a recursive copy of the given directory. Optionally a extension blacklist prevents files with the given extension(s) to be copied
     * @param string $sourceDir
     * @param string $destinationDir
     * @param array|null $extensionBlacklist: optional: if set, files with the given extensions will not be copied
     */
    public static function recursiveCopy(string $sourceDir, string $destinationDir, ?array $extensionBlacklist = null) {
        $dir = opendir($sourceDir);
        if(!file_exists($destinationDir)) {
            @mkdir($destinationDir);
        }
        while(false !== ( $file = readdir($dir)) ) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($sourceDir.DIRECTORY_SEPARATOR.$file)) {
                self::recursiveCopy(
                    $sourceDir.DIRECTORY_SEPARATOR.$file,
                    $destinationDir.DIRECTORY_SEPARATOR.$file,
                    $extensionBlacklist);
            } else {
                if($extensionBlacklist === null || !in_array(pathinfo($file, PATHINFO_EXTENSION), $extensionBlacklist)){
                    copy($sourceDir.DIRECTORY_SEPARATOR.$file, $destinationDir.DIRECTORY_SEPARATOR.$file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Deletes recursivly a directory. Optionally a extension whitelist can be passed that will only delete files with the given extension (and not the directories anymore, even if they are empty then)
     * @param string $directory
     * @param array|null $extensionWhitelist
     */
    public static function recursiveDelete(string $directory, ?array $extensionWhitelist = null){
        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }
            if ($fileinfo->isDir()) {
                self::recursiveDelete($directory.DIRECTORY_SEPARATOR.$fileinfo->getFilename());
            } else if($fileinfo->isFile() && ($extensionWhitelist === null || in_array(pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION), $extensionWhitelist))) {
                try {
                    unlink($directory.DIRECTORY_SEPARATOR.$fileinfo->getFilename());
                } catch (Exception){
                    error_log('ZfExtended_Utils::recursiveDelete: Could not delete file '.$directory.DIRECTORY_SEPARATOR.$fileinfo->getFilename());
                }
            }
        }
        if($extensionWhitelist === null){
            //FIXME try catch ist nur eine übergangslösung!!!
            try {
                rmdir($directory);
            } catch (Exception){
                error_log('ZfExtended_Utils::recursiveDelete: Could not delete directory '.$directory);
            }
        }
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

    /**
     * FIXME let the value come from a on deploy auto generated php file instead of reading the text version file
     * returns the application version
     * @param string $versionContent Optional, parses the version from thegiven text string
     * @return string
     */
    public static function getAppVersion(string $versionContent = null): string {
        $versionFile = APPLICATION_PATH.'/../version';
        $regex = '/MAJOR_VER=([0-9]+)\s*MINOR_VER=([0-9]+).*\s*BUILD=([0-9]+[a-z]?).*/';
        $matches = null;
        if(empty($versionContent) && file_exists($versionFile)) {
            $versionContent = file_get_contents($versionFile);
        }
        if(!empty($versionContent) && preg_match($regex, $versionContent, $matches)) {
            array_shift($matches);
            return join('.', $matches);
        }
        return self::VERSION_DEVELOPMENT;
    }

    /**
     * returns if the installation is a development installation
     * @return bool
     */
    public static function isDevelopment(): bool {
        return self::getAppVersion() === self::VERSION_DEVELOPMENT;
    }

    /***
     * Multibyte safe uppercase first function
     * @param string $string
     * @param string $encoding
     * @return string
     */
    public static function mb_ucfirst($string, $encoding='UTF-8'){
        $strlen = mb_strlen($string, $encoding);
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, $strlen - 1, $encoding);
        return mb_strtoupper($firstChar, $encoding) . $then;
    }

    /**
     * returns an hash unique for the current installation, based on IP Adress and used DB
     * @param string $salt
     */
    public static function installationHash(string $salt = '') {
        $db = Zend_Db_Table::getDefaultAdapter();
        $c = $db->getConfig();
        //FIXME on cluster installations this would fail, since the SERVER_ADDR is different for the different web servers.
        // no solution here, since putting a random value into the config would not prevent same ids on cloning installations.
        $ip = $_SERVER['SERVER_ADDR'] ?? '';
        return md5($salt.$ip.$c['host'].$c['username'].$c['dbname']);
    }

    /**
     * creates a real UUID (v4)
     * @return string
     */
    public static function uuid(): string {
        $rand = random_bytes(16);

        //see https://stackoverflow.com/a/15875555/1749200
        $rand[6] = chr(ord($rand[6]) & 0x0f | 0x40); // set version to 0100
        $rand[8] = chr(ord($rand[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($rand), 4));
    }

    /**
     * creates a guid as defined in the config (md5 or uuid v4), for historical reasons for some entities
     * @param bool addBrackets to add {}-brackets around the GUID, defaults to false
     * @return string $guid
     */
    public static function guid($addBrackets = false): string {
        $validator = new ZfExtended_Validate_Guid();
        switch (true) {
            //some intallations are using md5 formatted UUIDs
            case $validator->isValid('ca473dc489b0b126b3769cd8921b66b5'):
                return md5(random_bytes(32));
                //the default GUID format:
            case $validator->isValid('{C1D11C25-45D2-11D0-B0E2-201801180001}'):
            default:
                if ($addBrackets){
                    return '{' . self::uuid() . '}';
                }
                return self::uuid();
        };
    }
    
    /***
     * Remove byte order mark from string
     * @param string $text
     * @return string
     */
    public static function remove_utf8_bom(string $text):string{
        $bom = pack('H*','EFBBBF');
        return preg_replace("/^$bom/", '', $text);
    }

    /**
     * returns true if a string is empty (respects 0 casting problems, so a 0 string is not empty here)
     * @param string|null $mixed
     * @return bool
     */
    public static function emptyString(?string $mixed): bool {
        return is_null($mixed) || strlen($mixed) == 0;
    }

    /***
     * Add incremental number to file if the given $filename exist in $path
     * Ex: Filename.xliff -> Filename(1).xliff
     * @param string $filename
     * @param string $path
     * @return string
     */
    public static function addNumberIfExist(string $filename,string $path): string
    {
        $actual_name = pathinfo($filename,PATHINFO_FILENAME);
        $original_name = $actual_name;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if(!str_ends_with($path,DIRECTORY_SEPARATOR)){
            $path = $path.DIRECTORY_SEPARATOR;
        }
        $i = 1;
        while(file_exists($path.$actual_name.".".$extension))
        {
            $actual_name = $original_name.'('.$i.')';
            $filename = $actual_name.".".$extension;
            $i++;
        }
        return $filename;
    }

    /***
     * Parse the file extension for given file name
     * @param string $fileName
     * @return string
     */
    public static function getFileExtension(string $fileName): string
    {
        return pathinfo($fileName,PATHINFO_EXTENSION);
    }
}
