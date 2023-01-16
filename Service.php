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

namespace MittagQI\ZfExtended;

use Zend_Config;
use Zend_Registry;

/**
 * Represents a general Service used by a plugin or the base application
 * This usually is a Docker service but can generally represent any type of service outside the scope of the app code
 * This should not be mixed with a Languageresource-service, which in this is a special case of this general service (and unfortunately is not coded in a languageresource-specific scope)
 * Currently, this base-class is tailored for administrative purposes
 */
abstract class Service
{
    /**
     * This usually represents a dockerized service. The name is defined as array-key in a plugins $services prop
     * @var string
     */
    protected string $name;

    /**
     * A default port has to be defined for each service
     * @var int
     */
    protected int $port;

    /**
     * Holds the error when checking the service
     * @var string
     */
    protected string $error;

    /**
     * Represents the plugin-config in case of a plugin config, the global config otherwise
     * @var Zend_Config
     */
    protected Zend_Config $config;

    protected ?string $pluginName = null;

    /**
     * Holds the info if this is a plugin or global service
     * @var bool
     */
    private bool $isPlugin = false;

    /**
     * @param string $name: The name of the service, which MUST be unique across the application and all plugins
     * @param string|null $pluginName: If given, the service is a plugin service for the given plugin
     * @param Zend_Config|null $config: The global config
     */
    public function __construct(string $name, string $pluginName = null, Zend_Config $config = null)
    {
        $this->name = $name;
        $this->config = $config ?? Zend_Registry::get('config');
        $this->pluginName = $pluginName;
        $this->isPlugin = ($pluginName !== null);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isPluginService(): bool
    {
        return $this->isPlugin;
    }

    /**
     * Checks, if the service is set up properly and everything works. This will generate the errors/success messages that are accessible after calling this
     * @return bool
     */
    public function check(): bool
    {
        $this->error = 'Service "'.$this->getName().'" is not implemented properly';
        return false;
    }

    /**
     * Usually the port and host of the service are defined in the class (the host equals the http:// + our name)
     * For special situations or for multi-instance services, these values can be passed as params
     * @param array $config: may contains specific configs to set up the service
     * @return bool
     */
    public function locate(array $config = []): bool
    {
        return false;
    }

    /**
     * Administrative message if the service is not set up properly
     * This must specify what exactly does not work in case of complex services
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * Administrative message if the service is set up properly
     * @return string
     */
    public function getSuccess(): string
    {
        return 'Service "'.$this->getName().'" works as expected.';
    }

    /**
     * An administrative short description of the service
     * @return string
     */
    public function getDescription(): string
    {
        return 'TO BE DEFINED IN ' . get_class($this);
    }
}
