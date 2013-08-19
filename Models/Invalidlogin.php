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

/**#@+ 
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 * 
 */
/**
 * handles invalid logins
 *
 */
class ZfExtended_Models_Invalidlogin extends ZfExtended_Models_Db_Invalidlogin {
    /**
     * @var string 
     */
    protected $login;

    /**
     * Invalidlogin wird mit dem betroffenen Login / E-Mail-Adresse initialisiert
     * @param string $login
     */
    public function  __construct(string $login, $maximum = 3) {
        $this->login = $login;
        
        $config = Zend_Registry::get('config');
        if($maximum == 3 && isset($config->runtimeOptions->invalidLogin->maximum)){
            $maximum = $config->runtimeOptions->invalidLogin->maximum;
        }
        $this->maximum = $maximum;

        parent::__construct();
    }

    /**
     * erhöht den Invalid Login Counter eines Logins / einer login um eins, gibt $this zurück
     * @return void
     */
    public function increment(){
        $this->insert(
            array(
                'login' => strtolower($this->login),
            )
        );
    }

    /**
     * setzt den Invalid Login Counter eines Logins / einer login zurück, gibt $this zurück
     * @return void
     */
    public function resetCounter() {
        $where = $this->getAdapter()->quoteInto('login = ?', $this->login);
        $this->delete($where);
    }

    /**
     * gibt zurück ob eine Login / eine login-Adresse die maximale Anzahl an falschen Logins erreicht hat.
     * @return boolean
     */
    public function hasMaximumInvalidations() {
        $row = $this->fetchRow($this->select(array('invalidlogins' => 'COUNT(*)'))
                ->reset( Zend_Db_Select::COLUMNS )
                ->columns(array('invalidlogins' => 'COUNT(*)'))
                ->where('login = ? and created > (NOW() - INTERVAL 1 DAY)', $this->login));

        return ($row['invalidlogins'] >= $this->maximum);
    }
}