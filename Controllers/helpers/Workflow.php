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

/**
 * provides reusable workflow methods for controllers
 */
class ZfExtended_Controller_Helper_Workflow extends Zend_Controller_Action_Helper_Abstract {
    /**
     * checks the user state of given taskGuid and userGuid, 
     * throws a ZfExtended_NoAccessException if user is not allowed to write to the loaded task
     * @param string $taskGuid optional, if omitted we take the curently opened task from session
     * @param string $userGuid optional, if omitted we take the logged in user
     * @param editor_Workflow_Abstract $workflow optional, if omitted the configured workflow for task stored in the session is created
     * @throws ZfExtended_NoAccessException
     */
    public function checkWorkflowWriteable($taskGuid = null, $userGuid = null, editor_Workflow_Abstract $workflow = null) {
        if(empty($taskGuid)) {
            $s = new Zend_Session_Namespace();
            $taskGuid = $s->taskGuid;
        }
        if(empty($userGuid)) {
            $su = new Zend_Session_Namespace('user');
            $userGuid = $su->data->userGuid;
        }
        if(empty($workflow)) {
            //FIXME implementation sketch for TRANSLATE-113
            //$wfm = ZfExtended_Factory::get('editor_Workflow_Manager');
            //$workflow = $wfm->get($session->taskWorkflow);
            $workflow = ZfExtended_Factory::get('editor_Workflow_Default');
            /* @var $w editor_Workflow_Abstract */
        }
        $tua = $workflow->getTaskUserAssoc($taskGuid, $userGuid);
        if(! $workflow->isWritingAllowedForState($tua->getUsedState())) {
            //a ZfExtended_BadMethodCallException (405) would be correcter, 
            //but in frontend 403s are getting different text for different HTTP methods, so we take this
            throw new ZfExtended_NoAccessException();
        }
    }
}