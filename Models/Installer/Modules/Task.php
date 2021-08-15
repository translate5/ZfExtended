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
 * @package ZfExtended
 * @version 2.0
 * @deprecated
 */
class ZfExtended_Models_Installer_Modules_Help extends ZfExtended_Models_Installer_Modules_Abstract{
    public function __construct($options){
        /*
         * task ls → lists available tasks, id taskguid status taskNr taskName
         * → filtering?
         * task rm [ID]|[GUID]
         * task info [ID]|[GUID] prints information about a task
         * task import [ID]|[GUID] → starts import for that task (needed probably with decoupled task import start)
         * task unlock [ID]|[status]|[GUID] unlock a task identified by its ID or status or GUID
         */
    }
    public function run() {
        echo "draft";
    }
}