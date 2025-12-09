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
 * Database Controller provides simple actions to do basic database updating
 * @deprecated
 */
class DatabaseController extends ZfExtended_Controllers_Action
{
    public function importAction()
    {
        $this->view->importStarted = false;
        $dbupdater = ZfExtended_Factory::get('ZfExtended_Models_Installer_DbUpdater');
        /* @var $dbupdater ZfExtended_Models_Installer_DbUpdater */
        $dbupdater->calculateChanges();

        if (! empty($_GET['show'])) {
            $this->showContent($dbupdater, $_GET['show']);
            exit;
        }

        $errors = $dbupdater->getErrors(); //check for credential errors
        if (! empty($errors)) {
            $this->view->errors = $dbupdater->getErrors();

            return;
        }

        settype($_POST['startimport'], 'boolean');
        if ($_POST['startimport']) {
            $toProcess = $_POST;
            unset($toProcess['startimport']);
            $this->view->importStarted = true;
            if (empty($_POST['catchup'])) {
                $dbupdater->applyNew($toProcess);
                $dbupdater->updateModified($toProcess);
                $this->view->errors = $dbupdater->getErrors();
            } else {
                $dbupdater->assumeImported($toProcess);
            }
        }

        $dbupdater->calculateChanges();
        $this->view->sqlFilesNew = $dbupdater->getNewFiles();
        $this->view->sqlFilesChanged = $dbupdater->getModifiedFiles();
    }

    public function forceimportallAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $this->view->importStarted = false;

        $dbupdater = ZfExtended_Factory::get('ZfExtended_Models_Installer_DbUpdater');
        /* @var $dbupdater ZfExtended_Models_Installer_DbUpdater */
        $stat = $dbupdater->importAll();

        $errors = $dbupdater->getErrors();
        if (! empty($errors)) {
            echo "DB Import not OK\n";
            echo "Errors: \n";
            print_r($errors);

            return;
        }

        echo "DB Import OK\n";
        echo "  New statement files: " . $stat['new'] . "\n";
        echo "  Modified statement files: " . $stat['modified'] . "\n";
    }

    protected function showContent(ZfExtended_Models_Installer_DbUpdater $dbupdater, $hash)
    {
        $new = $dbupdater->getNewFiles();
        $modified = $dbupdater->getModifiedFiles();
        $all = array_merge($new, $modified);
        foreach ($all as $file) {
            if ($file->entryHash !== $hash) {
                continue;
            }
            echo '<pre>';
            echo htmlspecialchars(file_get_contents($file->absolutePath));
            echo '</pre>';

            return;
        }
    }

    public function catchupAction()
    {
        throw new BadMethodCallException('This method is obsolete! It is integrated now in the import Action!');
    }
}
