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
        
        $this->delete("created < '".date('Y-m-d h:i:s',time()-24*3600)."'");
    }

    /**
     * erhöht den Invalid Login Counter eines Logins / einer login um eins, gibt $this zurück
     * @return void
     */
    public function increment(){
        $this->insert(
            array(
                'login' => $this->login,
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