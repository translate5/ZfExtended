<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
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
        $log = ZfExtended_Factory::get('ZfExtended_Log');
        /* @var $log ZfExtended_Log */
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
}
