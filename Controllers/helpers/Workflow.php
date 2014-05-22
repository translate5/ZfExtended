<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

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