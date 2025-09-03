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

use MittagQI\ZfExtended\Tools\RecursiveFileDeletion;

/**
 * Defines some global functions.
 * Functions should be defines as public static function, so they can be calles as:
 *
 * ZfExtended_Utils::function()
 *
 * from everywhere in the application.
 */
class ZfExtended_Utils
{
    public const VERSION_DEVELOPMENT = 'development';

    /***
     * Convert the input date to mysql accepted date time format (Y-m-d H:i:s)
     * @param string $date
     * @return false|string
     */
    public static function toMysqlDateTime(string $date)
    {
        $timestamp = strtotime($date);
        if (empty($timestamp)) {
            return $date;
        }

        return date("Y-m-d H:i:s", $timestamp);
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
    public static function xpath($root, $path)
    {
        if (empty($path)) {
            return $root;
        }
        if (is_string($path)) {
            $path = explode('/', $path);
        }
        if (empty($root)) {
            return null;
        }
        $current = array_shift($path);
        $matches = null;
        if (preg_match('/^([^\[]+)\[([0-9]+)\]$/', $current, $matches)) {
            //since here we assume an array as next step
            $current = $matches[1];
            array_unshift($path, $matches[2]);
        }
        if (is_object($root)) {
            if (property_exists($root, $current)) {
                return self::xpath($root->$current, $path);
            }

            //error_log($current.' # '.print_r($root,1));
            return null;
        }
        if (is_array($root)) {
            if (array_key_exists($current, $root)) {
                return self::xpath($root[$current], $path);
            }
            $result = [];
            foreach ($root as $item) {
                $result[] = self::xpath($item, $path);
            }

            return $result;
        }

        return $root;
    }

    /**
     * Get an array of all class-constants-names from class $className which begin with $prefix.
     *
     * @return array of constants-names (key) and its values (value)
     */
    public static function getConstants(string $className, string $praefix)
    {
        $constants = [];

        $reflectionClass = new ReflectionClass($className);
        $classConstants = $reflectionClass->getConstants();

        foreach ($classConstants as $key => $value) {
            if (strpos($key, $praefix) === 0) {
                $constants[$key] = $value;
            }
        }

        return $constants;
    }

    /**
     * Checks, if a directory contans files
     */
    public static function hasFilesInDirectory(string $directoryPath): bool
    {
        foreach (new DirectoryIterator($directoryPath) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Retrieves the file-extensions of the files in the directory
     */
    public static function getExtensionsInDirectory(string $directoryPath): array
    {
        $extensions = [];
        foreach (new DirectoryIterator($directoryPath) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            $extensions[] = strtolower($fileInfo->getExtension());
        }

        return array_values(array_unique($extensions));
    }

    /**
     * Does a recursive copy of the given directory.
     * Optionally a extension blacklist prevents files with the given extension(s) to be copied
     * Second option is, to move the files instead copying them
     */
    public static function recursiveCopy(
        string $sourceDir,
        string $destinationDir,
        ?array $extensionBlacklist = null,
        bool $doMoveInsteadCopy = false,
    ) {
        $dir = opendir($sourceDir);
        if (! file_exists($destinationDir)) {
            if (! mkdir($destinationDir) && ! is_dir($destinationDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $destinationDir));
            }
        }
        while (false !== ($file = readdir($dir))) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            // SBE:BUGFIX: prevent endless-loop if destinationDir is inside sourceDir
            if ($sourceDir . DIRECTORY_SEPARATOR . $file == $destinationDir) {
                continue;
            }

            if (is_dir($sourceDir . DIRECTORY_SEPARATOR . $file)) {
                self::recursiveCopy(
                    $sourceDir . DIRECTORY_SEPARATOR . $file,
                    $destinationDir . DIRECTORY_SEPARATOR . $file,
                    $extensionBlacklist,
                    $doMoveInsteadCopy
                );
            } elseif ($extensionBlacklist === null ||
                ! in_array(pathinfo($file, PATHINFO_EXTENSION), $extensionBlacklist)
            ) {
                if ($doMoveInsteadCopy) {
                    rename($sourceDir . DIRECTORY_SEPARATOR . $file, $destinationDir . DIRECTORY_SEPARATOR . $file);
                } else {
                    copy($sourceDir . DIRECTORY_SEPARATOR . $file, $destinationDir . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Deletes recursively a directory. Optionally a extension whitelist can be passed that will only delete files with
     * the given extension. If a whitelist is given, no directories will be deleted. If a blacklist is given,
     * only empty directories will be deleted. Returns, if the passed directory was deleted.
     * HINT: Symlinks will not be deleted!
     * @param array|null $extensionWhitelist if set, only files of the given extensions are deleted.
     * This also prevents deleting any directories including the passed one
     * @param bool $whitelistIsBlacklist if set, the extension whitelist will be treated as blacklist leaving out
     * the defined extensions. This prevents the deletion of only those dirs, that are not empty therefore
     */
    public static function recursiveDelete(
        string $directory,
        ?array $extensionWhitelist = null,
        bool $whitelistIsBlacklist = false,
        bool $doDeletePassedDirectory = true
    ): bool {
        return (new RecursiveFileDeletion())->recursiveDelete(
            $directory,
            $extensionWhitelist,
            $whitelistIsBlacklist,
            $doDeletePassedDirectory,
        );
    }

    /**
     * cleans the filenames in zip containers up
     * @param string $prefixToRemove must be a directory inside the zip!
     */
    public static function cleanZipPaths(SplFileInfo $zipFile, $prefixToRemove)
    {
        $removalLength = mb_strlen($prefixToRemove);
        $zip = new ZipArchive();
        $zip->open($zipFile);
        $i = 0;
        while (true) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                break;
            }
            if (mb_strpos($name, $prefixToRemove) !== 0) {
                $i++;

                continue;
            }
            $newFileName = mb_substr($name, $removalLength + 1);
            if (empty($newFileName)) {
                $zip->deleteIndex($i); //we remove the empty directory which is stripped via prefixToRemove
            } else {
                $zip->renameIndex($i, $newFileName); //remove also the next / or \
            }
            $i++;
        }
        $zip->close();
    }

    /**
     * encodes the given utf8 filepath to the configured runtimeOptions.fileSystemEncoding
     * @return string $path
     * @see ZfExtended_Utils::filesystemEncode
     */
    public static function filesystemEncode(string $path)
    {
        $config = Zend_Registry::get('config');

        return iconv('UTF-8', $config->runtimeOptions->fileSystemEncoding, $path);
    }

    /**
     * decodes the given filepath in the configured runtimeOptions.fileSystemEncoding to utf8
     * @return string $path
     * @see ZfExtended_Utils::filesystemDecode
     */
    public static function filesystemDecode(string $path)
    {
        $config = Zend_Registry::get('config');

        return iconv($config->runtimeOptions->fileSystemEncoding, 'UTF-8', $path);
    }

    /**
     * FIXME let the value come from a on deploy auto generated php file instead of reading the text version file
     * returns the application version
     * @param string $versionContent Optional, parses the version from thegiven text string
     */
    public static function getAppVersion(string $versionContent = null): string
    {
        $versionFile = APPLICATION_PATH . '/../version';
        $regex = '/MAJOR_VER=([0-9]+)\s*MINOR_VER=([0-9]+).*\s*BUILD=([0-9]+[a-z]?).*/';
        $matches = null;
        if (empty($versionContent) && file_exists($versionFile)) {
            $versionContent = file_get_contents($versionFile);
        }
        if (! empty($versionContent) && preg_match($regex, $versionContent, $matches)) {
            array_shift($matches);

            return join('.', $matches);
        }

        return self::VERSION_DEVELOPMENT;
    }

    /**
     * returns if the installation is a development installation
     */
    public static function isDevelopment(): bool
    {
        return self::getAppVersion() === self::VERSION_DEVELOPMENT;
    }

    /***
     * Multibyte safe uppercase first function
     * @param string $string
     * @param string $encoding
     * @return string
     */
    public static function mb_ucfirst($string, $encoding = 'UTF-8')
    {
        $strlen = mb_strlen($string, $encoding);
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, $strlen - 1, $encoding);

        return mb_strtoupper($firstChar, $encoding) . $then;
    }

    /**
     * returns an hash unique for the current installation, based on IP Adress and used DB
     */
    public static function installationHash(string $salt = '')
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $c = $db->getConfig();
        //FIXME on cluster installations this would fail, since hostname is different for the different web servers.
        // no solution here, since putting a random value into the config would not prevent same ids on cloning installations.
        // addition: it depends on the use case: for worker:queue locking that would be ok,
        // so that queue can be triggered on each node
        $host = gethostname();

        return md5($salt . $host . $c['host'] . $c['username'] . $c['dbname']);
    }

    /**
     * creates a real UUID (v4)
     */
    public static function uuid(): string
    {
        $rand = random_bytes(16);

        //see https://stackoverflow.com/a/15875555/1749200
        $rand[6] = chr(ord($rand[6]) & 0x0f | 0x40); // set version to 0100
        $rand[8] = chr(ord($rand[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($rand), 4));
    }

    /**
     * creates a guid as defined in the config (md5 or uuid v4), for historical reasons for some entities
     * @param bool $addBrackets to add {}-brackets around the GUID, defaults to false
     * @return string $guid
     * @throws Exception
     */
    public static function guid(bool $addBrackets = false): string
    {
        $validator = new ZfExtended_Validate_Guid();
        switch (true) {
            //some intallations are using md5 formatted UUIDs
            case $validator->isValid('ca473dc489b0b126b3769cd8921b66b5'):
                return md5(random_bytes(32));
                //the default GUID format:
            case $validator->isValid('{C1D11C25-45D2-11D0-B0E2-201801180001}'):
            default:
                if ($addBrackets) {
                    return '{' . self::uuid() . '}';
                }

                return self::uuid();
        }
    }

    /***
     * Remove byte order mark from string
     * @param string $text
     * @return string
     */
    public static function remove_utf8_bom(string $text): string
    {
        $bom = pack('H*', 'EFBBBF');

        return preg_replace("/^$bom/", '', $text);
    }

    /**
     * returns true if a string is empty (respects 0 casting problems, so a 0 string is not empty here)
     */
    public static function emptyString(?string $mixed): bool
    {
        return is_null($mixed) || $mixed === '';
    }

    /***
     * Add incremental number to file if the given $filename exist in $path
     * Ex: Filename.xliff -> Filename(1).xliff
     * @param string $filename
     * @param string $path
     * @return string
     */
    public static function addNumberIfExist(string $filename, string $path): string
    {
        $actual_name = pathinfo($filename, PATHINFO_FILENAME);
        $original_name = $actual_name;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (! str_ends_with($path, DIRECTORY_SEPARATOR)) {
            $path = $path . DIRECTORY_SEPARATOR;
        }
        $i = 1;
        while (file_exists($path . $actual_name . "." . $extension)) {
            $actual_name = $original_name . '(' . $i . ')';
            $filename = $actual_name . "." . $extension;
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
        return pathinfo($fileName, PATHINFO_EXTENSION);
    }

    /***
     * Replace the co and c1 controll characters with empty space.
     */
    public static function replaceC0C1ControlCharacters(string $term): string
    {
        foreach (mb_str_split($term, 1, 'utf-8') as $ch) {
            $ord = mb_ord($ch);
            if ($ord === false || // Conversion failed
                (0 <= $ord && $ord <= 31) || // C0 control characters
                (128 <= $ord && $ord <= 159) || // C1 control characters
                $ord == 0x2028 || $ord == 0x2029 // Unicode newlines
            ) {
                $term = str_replace($ch, '', $term);
            }
        }

        return (string) $term;
    }

    /**
     * returns true if request is accepting JSON, needed in early bootstrapping where no Zend Methods are usable
     */
    public static function requestAcceptsJson(): bool
    {
        $headers = apache_request_headers();
        if (! $headers) {
            return false;
        }
        $headers = array_change_key_case($headers, CASE_LOWER);

        return ! empty($headers['accept']) && stripos($headers['accept'], 'json');
    }

    /***
     * Check if 2 (single-dimensional) arrays are equal
     * @param array $a
     * @param array $b
     * @return bool
     */
    public static function isArrayEqual(array $a, array $b): bool
    {
        return (count($a) === count($b) && ! array_diff($a, $b));
    }

    /**
     * returns true if the request was made with SSL.
     *  Our internal config server.protocol can not be used here,
     *  since the config resource is loaded after the session resource
     */
    public static function isHttpsRequest(): bool
    {
        //from https://stackoverflow.com/a/41591066/1749200
        // in SSL offloaded environments (SSL handled before our nginx proxy, it may happen that
        //  no such header is passed to identify the request as SSL request. In that cases we can just sppof such
        //  a header in our proxy config:
        //  # Since SSL is offloaded to the surrounding company proxy, we never get the info that SSL is used
        //  # so we just spoof that header here:
        //  proxy_set_header X-Forwarded-Proto https;
        return ((! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
            || (! empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443)
            || (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https')
        );
    }

    /**
     * Concatenates multiple pathes to one path like some/path/pf/parts taking into account the parts already may start or end with "/"
     */
    public static function combinePathes(?string ...$pathes): string
    {
        $combined = '';
        $lastIsSlash = false;
        foreach ($pathes as $path) {
            if (is_string($path) && strlen($path) > 0) {
                // catch first part is starting with '/'
                if ($combined === '' && str_starts_with($path, '/')) {
                    $combined = '/';
                    $path = substr($path, 1);
                }
                // add part
                $lastIsSlash = str_ends_with($path, '/');
                if (trim($path, '/') !== '') {
                    if ($combined !== '' && ! str_ends_with($combined, '/')) {
                        $combined .= '/';
                    }
                    $combined .= trim($path, '/');
                }
            }
        }

        return ($lastIsSlash && $combined !== '' && ! str_ends_with($combined, '/')) ? $combined . '/' : $combined;
    }

    /**
     * Get $desiredLocale arg if it's valid and available locale,
     * or get from applicationLocale-config,
     * or from browser,
     * or from fallbackLocale-config
     *
     * @throws Zend_Exception
     */
    public static function getLocale(?string $desiredLocale = ''): string
    {
        // Get [localeCode => localeName] pairs for valid locales
        $available = [
            'en' => true,
            'de' => true,
        ];
        /*$available = ZfExtended_Zendoverwrites_Translate
            ::getInstance()
            ->getAvailableTranslations();*/

        // If $desiredLocale is given, and it's valid and available - use it
        if ($desiredLocale) {
            if (Zend_Locale::isLocale($desiredLocale)) {
                if (isset($available[$desiredLocale])) {
                    return $desiredLocale;
                }
            }
        }

        // Get runtimeOptions and fallback locale from there
        $rop = Zend_Registry::get('config')->runtimeOptions;
        $fallback = $rop->translation->fallbackLocale;

        // If fallback is not available - then use first among available
        if (! isset($available[$fallback])) {
            $fallback = key($available);
        }

        // If app locate is set
        if ($appLocale = $rop->translation->applicationLocale) {
            // If it's valid - use that
            if (Zend_Locale::isLocale($appLocale) && isset($available[$appLocale])) {
                return $appLocale;

                // Else - report that and use fallback
            } else {
                error_log('Configured runtimeOptions.translation.applicationLocale is no valid locale, using ' . $fallback);

                return $fallback;
            }
        }

        // Else use browser language or fallback
        return self::getLocaleFromBrowser() ?: $fallback;
    }

    /**
     * Converts an integer-column value from the database either to an integer or null
     */
    public static function parseDbInt(mixed $dbInt): ?int
    {
        if (is_null($dbInt)) {
            return null;
        }
        if (is_int($dbInt)) {
            return $dbInt;
        }

        return intval($dbInt);
    }

    /**
     * Adds a Condition to a Zend select, that ditinguishes between "IN" and "=" depending on the count
     * The column argument is expected to be fully qualified if neccessary like "table.column"
     */
    public static function addArrayCondition(Zend_Db_Table_Select $select, array $values, string $column): void
    {
        if (count($values) > 1) {
            $select->where($column . ' IN (?)', $values);
        } elseif (count($values) === 1) {
            $select->where($column . ' = ?', $values[0]);
        } else {
            $select->where('1 = 0');
        }
    }

    /**
     * Moved from ZfExtended_Controllers_Plugins_LocaleSetup
     *
     * gets locale from browser
     * @return string
     */
    protected static function getLocaleFromBrowser()
    {
        $config = Zend_Registry::get('config');
        $localeObj = new Zend_Locale();
        $userPrefLangs = array_keys($localeObj->getBrowser());
        if (count($userPrefLangs) > 0) {
            //Prüfe, ob für jede locale, ob eine xliff-Datei vorhanden ist - wenn nicht fallback
            foreach ($userPrefLangs as $testLocale) {
                $testLocaleObj = new Zend_Locale($testLocale);
                $testLang = $testLocaleObj->getLanguage();
                $localePath = $config->runtimeOptions->dir->locales . DIRECTORY_SEPARATOR . $testLang .
                    ZfExtended_Zendoverwrites_Translate::DOT_EXTENSION;
                if (file_exists($localePath)) {
                    return $testLang;
                }
            }
        }
    }

    /**
     * Clean a string from non-breaking spaces and trim it
     */
    public static function cleanString(string $text): string
    {
        // Replace non-breaking spaces with regular spaces
        $text = str_replace("\xc2\xa0", ' ', $text);
        // Replace all whitespace characters with regular spaces
        $text = preg_replace('/\s/', ' ', $text);

        // Trim leading and trailing whitespaces
        return trim($text);
    }

    /**
     * Renames an existing log-file to an older version - if there is one
     * The naming-scheme is as: php.log, php-1.log, php-2.log, ... with -1 being the oldest
     */
    public static function versionExistingLogFile(string $filePath): bool
    {
        if (file_exists($filePath)) {
            $extension = self::getFileExtension($filePath);
            $pathNoExtension = substr($filePath, 0, -1 * (strlen($extension) + 1));
            $count = 1;
            while (file_exists($pathNoExtension . '-' . $count . '.' . $extension)) {
                $count++;
            }
            rename($filePath, $pathNoExtension . '-' . $count . '.' . $extension);

            return true;
        }

        return false;
    }

    /**
     * Writes a log-file
     * The naming-scheme is as: php.log, php-1.log, php-2.log, ... with -1 being the oldest
     */
    public static function saveVersionedLogFile(string $filePath, string $logDdata): bool|int
    {
        self::versionExistingLogFile($filePath);

        return file_put_contents($filePath, $logDdata);
    }
}
