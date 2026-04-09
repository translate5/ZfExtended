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

use MittagQI\ZfExtended\Localization;
use MittagQI\ZfExtended\Session\SessionInternalUniqueId;
use ZfExtended_Authentication as Auth;
use ZfExtended_Models_User as User;

/**
 * methods needed vor login and password handling
 */
abstract class ZfExtended_Controllers_Login extends ZfExtended_Controllers_Action
{
    use ZfExtended_Controllers_MaintenanceTrait;

    /**
     * @var Zend_Session_Namespace
     */
    protected $_session;

    /**
     * @var ZfExtended_Zendoverwrites_Translate
     */
    protected $_translate;

    /**
     * @var ZfExtended_Zendoverwrites_Form
     */
    protected $_form;

    public function init()
    {
        parent::init();
        $this->_translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        $this->_session = new Zend_Session_Namespace();
    }

    /**
     * does the login-handling
     *
     * - checks if login has been blocked
     * - blocks login, if necessary
     */
    public function indexAction()
    {
        $this->_form->setTranslator($this->_translate);
        $this->view->form = $this->_form;
        //if the user click on the openid redirect link in the login form
        if ($this->isOpenIdRedirect()) {
            //set login status to 'login needed'
            $this->view->loginStatus = ZfExtended_Authentication::LOGIN_STATUS_OPENID;

            return;
        }
        if ($this->isMaintenanceLoginLock()) {
            //set login status to 'maintenance'
            $this->_form->addError($this->_translate->_('We’re about to start maintenance, so logins are temporarily unavailable. Please try again in a few minutes.'));
            $this->view->loginStatus = ZfExtended_Authentication::LOGIN_STATUS_MAINTENANCE;

            return;
        }

        if ($this->isLoginRequest()) {
            //set the translate5 login status
            $this->view->loginStatus = $this->isValidLogin() ? ZfExtended_Authentication::LOGIN_STATUS_SUCCESS : ZfExtended_Authentication::LOGIN_STATUS_REQUIRED;

            return;
        }
        //redirect the user if the session contains already a user
        if (ZfExtended_Authentication::getInstance()->isAuthenticated()) {
            //set login status to 'authenticated'
            $this->view->loginStatus = ZfExtended_Authentication::LOGIN_STATUS_AUTHENTICATED;
            $this->initDataAndRedirect();

            return;
        }

        //set login status to 'login with openid'
        $this->view->loginStatus = ZfExtended_Authentication::LOGIN_STATUS_OPENID;
    }

    /**
     * @deprecated
     * returns a REST like login status information.
     * HTTP 200 and a JSON Representation of the user if authenticated
     * HTTP 404 (!=200) if not authenticated
     */
    public function statusAction()
    {
        throw new BadMethodCallException('"API login/status" is deprecated use "API session/SESSIONID" instead.');
    }

    /**
     * checks if login-request is made
     * @return boolean
     */
    protected function isLoginRequest()
    {
        return ($this->getRequest()->getParam('login') || $this->getRequest()->getParam('passwd'));
    }

    /**
     * checks if login-request is valid and does corresponding handling
     * @return boolean
     * @throws ReflectionException
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Db_Table_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    protected function isValidLogin(): bool
    {
        if (! $this->_form->isValid($this->_request->getParams())) {
            return false;
        }
        $login = trim($this->_form->getValue('login'));
        $passwd = trim($this->_form->getValue('passwd'));
        //ensure that empty passwd can never pass, regardless of what is defined in login.ini, because passwd-default is null in db
        if ($passwd == '') {
            return false;
        }
        $invalidLoginCounter = ZfExtended_Factory::get(ZfExtended_Models_Invalidlogin::class);
        if ($this->hasMaximumInvalidations($invalidLoginCounter, $login)) {
            return false;
        }
        if ($this->hasUserAlreadyASession($login)) {
            return false;
        }

        $auth = Auth::getInstance();
        if ($auth->authenticate($login, $passwd)) {
            //reset login counter on login success
            $invalidLoginCounter->resetCounter($login);

            ZfExtended_Models_LoginLog::addSuccess($auth, 'plainlogin');

            // check for already valid session for the current authenticated user
            ZfExtended_Session::updateSession(true, true, intval($auth->getUser()->getId()));

            $this->initDataAndRedirect();

            return true;
        }
        ZfExtended_Models_LoginLog::addFailed($login, "plainlogin");
        $invalidLoginCounter->increment($login);
        if ($this->hasMaximumInvalidations($invalidLoginCounter, $login)) {
            return false;
        }
        $this->view->errors = true;
        $this->_form->addError(sprintf(
            $this->_translate->_('We couldn’t log you in.<br/>Forgot your password, or haven’t set one yet? Request a new link %shere%s anytime.'),
            '<a href="' . APPLICATION_RUNDIR . '/login/passwdreset">',
            '</a>'
        ));

        return false;
    }

    /**
     * ensures - if enabled by configuration - that a unique user is only logged in once
     * is enabled by setting $config->runtimeOptions->singleUserRestriction to true
     * @param string $login
     * @return boolean
     */
    protected function hasUserAlreadyASession($login)
    {
        $config = Zend_Registry::get('config');
        if (! $config->runtimeOptions->singleUserRestriction) {
            return false;
        }
        $lock = ZfExtended_Factory::get('ZfExtended_Models_Db_SessionUserLock');
        /* @var $lock ZfExtended_Models_Db_SessionUserLock */

        try {
            $lock->insert([
                'login' => $login,
                'internalSessionUniqId' => SessionInternalUniqueId::getInstance()->get(),
            ]);

            return false;
        } catch (Zend_Db_Statement_Exception $e) {
            $m = $e->getMessage();
            $isSqlState = strpos($m, 'SQLSTATE') === 0;
            $isMissingLogin = stripos($m, 'FOREIGN KEY (`login`)') !== false && stripos($m, 'a foreign key constraint fails') !== false;
            //if entered a missing login we get an contraint error here,
            //we return false here since this user does not have a session.
            //The not existence of the login is then checked correctly later.
            if ($isSqlState && $isMissingLogin) {
                return false;
            }
            //if error is no "duplicate entry" error we throw it regularly!
            if (! $isSqlState || stripos($m, 'Duplicate entry') === false) {
                throw $e;
            }
        }

        $this->view->errors = true;
        $this->_form->addError($this->_translate->_('This user already is in use. <br/>Please wait until it is available again or use another user!'));

        return true;
    }

    /**
     * @throws ReflectionException
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ZfExtended_ValidateException
     */
    protected function hasMaximumInvalidations(ZfExtended_Models_Invalidlogin $invalidLogin, string $login): bool
    {
        if ($invalidLogin->hasMaximumInvalidations($login)) {
            $passwdreset = ZfExtended_Factory::get('ZfExtended_Models_Passwdreset');
            /* @var $passwdreset ZfExtended_Models_Passwdreset */
            $passwdreset->reset($this->_form->getValue('login'), __FUNCTION__);
            $this->view->errors = true;
            $this->_form->addError(sprintf(
                $this->_translate->_('Invalid login credentials – access locked!<br/>You have entered your password incorrectly several times in the last 24 hours, so your login has been locked.<br />An email has been sent to you with a link to reset your password. This link is valid for 30 minutes and works only as long as you have not closed your browser in the meantime. You can request a new link %shere%s at any time.'),
                '<a href="' . APPLICATION_RUNDIR . '/login/passwdreset">',
                '</a>'
            ));

            return true;
        }

        return false;
    }

    /***
     * Check if the current request is openid redirect
     * @return boolean
     */
    protected function isOpenIdRedirect()
    {
        return $this->getRequest()->getParam('openidredirect') != null && $this->getRequest()->getParam('openidredirect') == 'openid';
    }

    abstract protected function initDataAndRedirect();

    /**
     * deletes all sessiondata
     *
     * - redirects to root
     * - if($this->getRequest()->getParam('openidredirect', true) == false, no redirection is done
     */
    public function logoutAction()
    {
        // Shutdown view script and layout
        $this->_helper->viewRenderer->setNoRender(true);
        Zend_Layout::getMvcInstance()->disableLayout();

        $this->doOnLogout();
        Auth::getInstance()->logoutUser();
        $this->postDispatch(); //trigger after action events before redirecting
        if ($this->getRequest()->getParam('noredirect')) {
            //on sendBeacon logouts we do not want any redirect, so just exit.
            exit();
        }
        if ($this->getRequest()->getParam('openidredirect', true)) {
            header('Location: ' . APPLICATION_RUNDIR . '/');
            exit();
        }
    }

    /**
     * additional handling on logout
     */
    protected function doOnLogout()
    {
    }

    /**
     * reset password and sent hash to set new password by mail
     */
    public function passwdresetAction()
    {
        $this->_form = new ZfExtended_Zendoverwrites_Form('loginPasswdreset.ini');
        $this->_form->setTranslator($this->_translate);

        if ($this->getRequest()->getParam('login') && $this->_form->isValid($this->_request->getParams())) {
            $passwdreset = ZfExtended_Factory::get('ZfExtended_Models_Passwdreset');
            /* @var $passwdreset ZfExtended_Models_Passwdreset */
            $passwdreset->reset($this->_form->getValue('login'), __FUNCTION__);
            $this->view->message = $this->_translate->_('A link for resetting your password has been sent to you via e-mail. This link is valid for 30 minutes and will work only if you have not closed your browser in the meantime. You can request a new link at any time using this form.');
        }
        $this->view->form = $this->_form;
    }

    /**
     * sets the password and passwdReset to false in the LEK_user-table
     *
     * - needs a resetHash as getParam, as for this user is stored in the database
     * - on succes shows login
     *
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ZfExtended_Models_Entity_NotFoundException
     * @throws ReflectionException
     */
    public function passwdnewAction()
    {
        $this->view->passwdResetInfo = false;
        if ($this->getRequest()->getParam('resetHash', false)) {
            $this->_form = new ZfExtended_Zendoverwrites_Form('loginPasswdnew.ini');
            $this->_form->setTranslator($this->_translate);
            $md5Validator = new ZfExtended_Validate_Md5();
            if (! $md5Validator->isValid($this->getRequest()->getParam('resetHash'))) {
                $this->passwdResetHashNotValid();

                return;
            }

            $resetHashElement = $this->_form->getElement('resetHash');
            $resetHashElement->setValue($this->getRequest()->getParam('resetHash'));

            // trim the whitespaces from the request passwd param
            $this->getRequest()->setParam('passwd', trim($this->getRequest()->getParam('passwd', false)));
            // trim the whitespaces from the request passwdCheck param
            $this->getRequest()->setParam('passwdCheck', trim($this->getRequest()->getParam('passwdCheck', false)));

            if ($this->isNewPasswordValid()) {
                $passwdreset = ZfExtended_Factory::get('ZfExtended_Models_Passwdreset');
                /* @var ZfExtended_Models_Passwdreset $passwdreset */
                $passwdreset->deleteOldHashes();

                if (! $passwdreset->hashMatches($this->_form->getValue('resetHash'))) {
                    $this->passwdResetHashNotValid();

                    return;
                }

                $user = ZfExtended_Factory::get(User::class);
                $user->load((int) $passwdreset->getUserId());
                $pwd = trim($this->_form->getValue('passwd'));
                $pwd = ZfExtended_Authentication::getInstance()->createSecurePassword($pwd);
                $user->setPasswd($pwd);
                $user->save();

                $invalidLogin = ZfExtended_Factory::get(ZfExtended_Models_Invalidlogin::class);
                $invalidLogin->resetCounter($user->getLogin());

                $this->_form = new ZfExtended_Zendoverwrites_Form('loginIndex.ini');
                $this->_form->setTranslator($this->_translate);
                $this->view->heading = $this->_translate->_('Login');
                $this->view->message = $this->_translate->_('Your password has been reset. You can log in now.');
            }
            $this->view->form = $this->_form;

            return;
        }
        $this->redirect('/login');
    }

    /**
     * render passwdNew when resetHash not valid
     */
    protected function passwdResetHashNotValid()
    {
        $this->_form = new ZfExtended_Zendoverwrites_Form('loginPasswdreset.ini');
        $this->_form->setTranslator($this->_translate);
        $this->view->errors = true;
        $this->_form->addError($this->_translate->_('The ResetHash or the browser session is not valid (anymore). Please request a new link via e-mail by using the below form.'));
        $this->view->form = $this->_form;
        $this->render('passwdreset');
    }

    /**
     * updates the session locale by the locale stored in the DB for this user
     * @throws Zend_Locale_Exception
     */
    protected function localeSetup(): void
    {
        $user = ZfExtended_Authentication::getInstance()->getUser();
        $locale = $user?->getLocale();
        if ($locale !== null && Localization::isAvailableLocale($locale)) {
            $zendLocale = new Zend_Locale($locale);
            // save locale in session
            $this->_session->locale = $locale;
            // set locale as Zend App default locale
            Zend_Registry::set('Zend_Locale', $zendLocale);
        } else {
            // if the user has no valid locale in the DB we set the current locale either by a (valid) session locale
            // or the general fallback-locale. This will not overwrite the current user's locale (!)
            $locale = (! empty($this->_session->locale) && Localization::isAvailableLocale($this->_session->locale)) ?
                $this->_session->locale : Localization::FALLBACK_LOCALE;
            Auth::getInstance()->getUser()->setLocale($locale);
        }
    }

    /**
     * Check if the new password meets the password requirements
     * @throws Zend_Form_Exception
     */
    private function isNewPasswordValid(): bool
    {
        $password = $this->getRequest()->getParam('passwd', false);

        $message = [];
        $isValid = ZfExtended_PasswordCheck::isValid($password, $message);

        if (! empty($message)) {
            $this->_form->setErrors($message);
        }

        return $isValid && $this->_form->isValid($this->_request->getParams());
    }
}
