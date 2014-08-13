<?php
 /*
 START LICENSE AND COPYRIGHT
 
 This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
 
  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html
 
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
        
        settype($_POST['startimport'], 'boolean');
        if($_POST['startimport']) {
            $this->view->importStarted = true;
            $dbupdater->applyNew();
            $dbupdater->updateModified();
            $this->view->errors = $dbupdater->getErrors();
        }
        
        $this->view->sqlFilesNew = $dbupdater->getNewFiles();
        $this->view->sqlFilesChanged = $dbupdater->getModifiedFiles();
    }
    
    public function catchupAction() {
        $dbupdater = ZfExtended_Factory::get('ZfExtended_Models_Installer_DbUpdater');
        $dbupdater->assumeAllImported();
        echo "all files are marked as imported now!";exit;
    }
}
