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
 */
class  ZfExtended_Zendoverwrites_Filter_Compress_Zip extends Zend_Filter_Compress_Zip {

    /**
     * Compression Options
     * array(
     *     'archive'  => Archive to use
     *     'target'   => Target to write the files to
     * )
     *
     * @var array
     */
    protected $_options = [
        'archive' => null,
        'target'  => null,
        'copyRootFolder' => true,
    ];

    /***
     * Should the root folder be copied in the target zip archive.
     * This only works if the source is directory (not file)
     * Ex:
     * The folder to compress is /var/www/translate5/data/taskGuid/importDirectory/
     * when the flag is set to true, the zip archive will contain importDirectory as root folder.
     * If set to false, the content inside the importDirectory will be copied
     * @var bool
     */
    private bool $copyRootFolder = true;

    public function __construct($options = null)
    {
        parent::__construct($options);
    }

    /**
     * Compresses the given content
     *
     * @param  string $content
     * @return string Compressed archive
     */
    public function compress($content)
    {
        $zip = new ZipArchive();
        $res = $zip->open($this->getArchive(), ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($res !== true) {
            require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception($this->_errorString($res));
        }

        if (file_exists($content)) {
            $content  = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, realpath($content));
            $basename = substr($content, strrpos($content, DIRECTORY_SEPARATOR) + 1);
            if (is_dir($content)) {

                // check if the root folder copy is enabled/disabled
                if($this->isCopyRootFolder() === false){
                    // disabled -> copy all content inside the $content
                    $content .= DIRECTORY_SEPARATOR;
                    $index    = strrpos($content, DIRECTORY_SEPARATOR) + 1;
                }else{
                    // enabled (this is the default option) -> copy all the content including the root folder
                    $index    = strrpos($content, DIRECTORY_SEPARATOR) + 1;
                    $content .= DIRECTORY_SEPARATOR;
                }

                $stack    = [$content];
                while (!empty($stack)) {
                    $current = array_pop($stack);
                    $files   = [];

                    $dir = dir($current);
                    while (false !== ($node = $dir->read())) {
                        if (($node == '.') || ($node == '..')) {
                            continue;
                        }

                        if (is_dir($current . $node)) {
                            array_push($stack, $current . $node . DIRECTORY_SEPARATOR);
                        }

                        if (is_file($current . $node)) {
                            $files[] = $node;
                        }
                    }

                    $local = substr($current, $index);
                    $zip->addEmptyDir(substr($local, 0, -1));

                    foreach ($files as $file) {
                        $zip->addFile($current . $file, $local . $file);
                        if ($res !== true) {
                            require_once 'Zend/Filter/Exception.php';
                            throw new Zend_Filter_Exception($this->_errorString($res));
                        }
                    }
                }
            } else {
                $res = $zip->addFile($content, $basename);
                if ($res !== true) {
                    require_once 'Zend/Filter/Exception.php';
                    throw new Zend_Filter_Exception($this->_errorString($res));
                }
            }
        } else {
            $file = $this->getTarget();
            if (!is_dir($file)) {
                $file = basename($file);
            } else {
                $file = "zip.tmp";
            }

            $res = $zip->addFromString($file, $content);
            if ($res !== true) {
                require_once 'Zend/Filter/Exception.php';
                throw new Zend_Filter_Exception($this->_errorString($res));
            }
        }

        $zip->close();
        return $this->_options['archive'];
    }

    /**
     * @return bool
     */
    public function isCopyRootFolder(): bool
    {
        return $this->copyRootFolder;
    }

    /**
     * @param bool $copyRootFolder
     */
    public function setCopyRootFolder(bool $copyRootFolder): void
    {
        $this->copyRootFolder = $copyRootFolder;
        $this->_options['copyRootFolder'] = $copyRootFolder;
    }
}
