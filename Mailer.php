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

/**
 */
class  ZfExtended_Mailer extends Zend_Mail {
    
    
    /**
     * disable sending E-Mails completly
     * @var boolean
     */
    protected $sendingDisabled = false;
    
    /**
     * @var Zend_Config
     */
    protected $config;
    
    
    /**
     * Public constructor
     *
     * @param  string $charset
     */
    public function __construct($charset = null) {
        $this->config = Zend_Registry::get('config');
        $this->sendingDisabled = $this->config->runtimeOptions->sendMailDisabled;
        parent::__construct($charset);
    }
    
    /**
     * Sends this email using the given transport or a previously
     * set DefaultTransport or the internal mail function if no
     * default transport had been set.
     *
     * @param  Zend_Mail_Transport_Abstract $transport
     * @return Zend_Mail                    Provides fluent interface
     */
    public function send($transport = null){
        if($this->sendingDisabled){
            if(ZfExtended_Debug::hasLevel('core', 'mailing')){
                error_log('translate5 disabled mail: '.$this->getSubject().' <'.implode(',',$this->getRecipients()).'>');
            }
            return null;
        }
        
        try {
            return parent::send($transport);
        } catch (Zend_Mail_Transport_Exception $e) {
            //TODO: logger, talk with Thomas
        }
        return null;
    }
}
