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

/* * #@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/*
 * Methoden, um Verzeichnisse aufzuräumen / zu löschen
 *
 *
 */

class ZfExtended_Controller_Helper_Recursivedircleaner extends Zend_Controller_Action_Helper_Abstract {
    /*
     * @param string $directory Pfad zum rekursiv mit allen Inhalten zu löschenden Verzeichnis
     */

    public function delete(string $directory) {
        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }
            if ($fileinfo->isDir()) {
                $this->delete($directory . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
            }
            if ($fileinfo->isFile()) {
                try {
                    unlink($directory . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
                }
                catch (Exception $e){
                       
                }
            }
        }
        //FIXME try catch ist nur eine übergangslösung!!!
        try {
            rmdir($directory);
        }
        catch (Exception $e){

        }
    }
    
    /***
     * Remove files older than the given timestamp
     *
     * @param string $directory
     * @param int $olderThan
     */
    public function deleteOldFiles(string $directory,int $olderThan=null) {
        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            if ($fileInfo->isDir()) {
                $this->deleteOldFiles($directory . DIRECTORY_SEPARATOR . $fileInfo->getFilename(),$olderThan);
            }
            
            if ($fileInfo->isFile() && filemtime($fileInfo->getRealPath()) < $olderThan) {
                unlink($fileInfo->getRealPath());
            }
        }
    }
}
