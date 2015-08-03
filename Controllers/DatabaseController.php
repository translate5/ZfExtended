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

/**
 * Database Controller provides simple actions to do basic database updating
 */
class DatabaseController extends ZfExtended_Controllers_Action {
    public function importAction() {
        $this->view->importStarted = false;
        $dbupdater = ZfExtended_Factory::get('ZfExtended_Models_Installer_DbUpdater');
        /* @var $dbupdater ZfExtended_Models_Installer_DbUpdater */
        $dbupdater->calculateChanges();

        if(!empty($_GET['show'])) {
            $this->showContent($dbupdater, $_GET['show']);
            exit;
        }
        
        settype($_POST['startimport'], 'boolean');
        if($_POST['startimport']) {
            $toProcess = $_POST;
            unset($toProcess['startimport']);
            $this->view->importStarted = true;
            if(empty($_POST['catchup'])) {
                $dbupdater->applyNew($toProcess);
                $dbupdater->updateModified($toProcess);
                $this->view->errors = $dbupdater->getErrors();
            }
            else {
                $dbupdater->assumeImported($toProcess);
            }
        }
        
        $dbupdater->calculateChanges();
        $this->view->sqlFilesNew = $dbupdater->getNewFiles();
        $this->view->sqlFilesChanged = $dbupdater->getModifiedFiles();
    }
    
    public function forceimportallAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        
        $this->view->importStarted = false;
        
        $dbupdater = ZfExtended_Factory::get('ZfExtended_Models_Installer_DbUpdater');
        /* @var $dbupdater ZfExtended_Models_Installer_DbUpdater */
        $stat = $dbupdater->importAll();
        
        $errors = $dbupdater->getErrors();
        if(!empty($errors)) {
            echo "DB Import not OK\n";
            echo "Errors: \n";
            print_r($errors);
            return;
        }
        
        echo "DB Import OK\n";
        echo "  New statement files: ".$stat['new']."\n";
        echo "  Modified statement files: ".$stat['modified']."\n";
    }
    
    protected function showContent(ZfExtended_Models_Installer_DbUpdater $dbupdater, $hash) {
        $new = $dbupdater->getNewFiles();
        $modified = $dbupdater->getModifiedFiles();
        $all = array_merge($new, $modified);
        foreach($all as $file) {
            if($file['entryHash'] !== $hash) {
                continue;
            }
            echo '<pre>';
            echo htmlspecialchars(file_get_contents($file['absolutePath']));
            echo '</pre>';
            return;
        }
    }
    
    public function catchupAction() {
        throw new BadMethodCallException('This method is obsolete! It is integrated now in the import Action!');
    }
}
