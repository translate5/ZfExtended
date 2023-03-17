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

namespace MittagQI\ZfExtended\Service;

use Zend_Config;
use Zend_Registry;
use Zend_Exception;
use ZfExtended_Models_SystemRequirement_Result;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Represents a general Service used by a plugin or the base application
 * This usually is a Docker service but can generally represent any type of service outside the scope of the app code
 * This should not be mixed with a Languageresource-service, which in this is a special case of this general service (and unfortunately is not coded in a languageresource-specific scope)
 * Currently, this base-class is tailored for administrative purposes
 */
abstract class ServiceAbstract
{
    const DO_DEBUG = false;
    /**
     * This usually represents a dockerized service. The name is defined as array-key in a plugins $services prop and must be UNIQUE across all plugins and the base services
     * @var string
     */
    protected string $name;

    /**
     * Holds the warnings when checking the service
     * @var string[]
     */
    protected array $warnings = [];

    /**
     * Holds the errors when checking the service
     * @var string[]
     */
    protected array $errors = [];

    /**
     * Holds a special summary giving hints to fix when checking the service
     * @var string[]
     */
    protected array $badSummary = [];

    /**
     * Holds the checked URLs
     * @var string[]
     */
    protected array $checkedUrls = [];

    /**
     * Holds the service-versions of the checked URLs
     * @var string[]
     */
    protected array $checkedVersions = [];

    /**
     * Represents the the global config
     * @var Zend_Config
     */
    protected Zend_Config $config;

    /**
     * @var string|null
     */
    protected ?string $pluginName = null;

    /**
     * Defines, if the service can be located
     * @var bool
     */
    protected bool $locatable = true;

    /**
     * Holds the info if this is a plugin or global service
     * @var bool
     */
    private bool $isPlugin;

    /**
     * @param string $name : The name of the service, which MUST be unique across the application and all plugins
     * @param string|null $pluginName : If given, the service is a plugin service for the given plugin
     * @param Zend_Config|null $config : The global config
     * @throws Zend_Exception
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
     * @return bool
     */
    public function canBeLocated(): bool
    {
        return $this->locatable;
    }

    /**
     * @return string|null
     */
    public function getPluginName(): ?string
    {
        return $this->pluginName;
    }

    /**
     * Checks, if the service is set up properly and everything works.
     * This will generate the errors/success messages that are accessible after calling this
     * Must be implemented for each service
     * @return bool
     */
    public function check(): bool
    {
        $this->errors[] = 'Service "' . $this->getName() . '" is not implemented';
        return false;
    }

    /**
     * Evaluates, if a service may should be skipped for checks
     * This can be used to exclude services based on config-dependent decisions
     * @return bool
     */
    public function isCheckSkipped(): bool
    {
        return false;
    }

    /**
     * Creates a result in the system-check style
     * @return ZfExtended_Models_SystemRequirement_Result
     */
    public function systemCheck(): ZfExtended_Models_SystemRequirement_Result
    {
        $result = new ZfExtended_Models_SystemRequirement_Result();
        $result->id = $this->name;
        if (!$this->check()) {
            $result->warning = $this->warnings;
            $result->error = $this->errors;
            $result->badSummary = $this->badSummary;
        }
        $result->name = $this->getDescription() . $this->getCheckedDetail(', ', 'version', $this->checkedVersions);
        return $result;
    }

    /**
     * outputs a simple check in the Symfony Command style
     * @param SymfonyStyle $io
     */
    public function serviceCheck(SymfonyStyle $io)
    {
        if ($this->isCheckSkipped()) {
            $this->output($this->getIrrelevant(), $io, 'info');
        } else if ($this->check()) {
            $this->output($this->getSuccess(), $io, 'success');
        } else {
            $this->output($this->getError(), $io, 'caution');
        }
        $this->hasWarnings() && $this->output($this->getWarning(), $io, 'warning');
    }

    /**
     * Sets the Service up in the context of an symfony Command
     * Usually the port and host of the service are defined in the class (the host equals the http:// + our name)
     * For special situations or for pooled services, these values can be passed as params
     * @param SymfonyStyle $io
     * @param mixed $url
     * @param bool $doSave
     * @param array $config : optional to inject further dependencies
     * @return bool
     */
    public function locate(SymfonyStyle $io, mixed $url, bool $doSave = false, array $config = []): bool
    {
        $this->output('TO BE IMPLEMENTED IN ' . get_class($this), $io, 'error');
        return false;
    }

    /**
     * Administrative message if the service is not set up properly
     * This must specify what exactly does not work in case of complex services
     * @param string $seperator
     * @return string
     */
    public function getError(string $seperator = "\n"): string
    {
        return
            $this->createServiceMsg('is not properly working:', $seperator)
            . $seperator . $seperator
            . implode($seperator, $this->errors);
    }

    /**
     * Administrative message if the service is set up properly
     * @param string $seperator
     * @return string
     */
    public function getSuccess(string $seperator = "\n"): string
    {
        return $this->createServiceMsg('works as expected.', $seperator);
    }

    /**
     * Administrative message if an optional service is not set up properly
     * @param string $seperator
     * @return string
     */
    public function getWarning(string $seperator = "\n"): string
    {
        return
            $this->getDescription()
            . ' has warnings:'
            . $seperator . $seperator
            . implode($seperator, $this->warnings);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Administrative message if the service is set up properly
     * @return string
     */
    public function getIrrelevant(): string
    {
        return $this->getDescription() . ' is not relevant for the current configuration.';
    }

    public function createServiceMsg(string $msg, string $seperator, bool $withUrls=true, bool $withVersions=true): string
    {
        $text = $this->getDescription();
        if(!empty($msg)){
            $text .= ' ' . $msg;
        }
        if($withUrls){
            $text .= $this->getCheckedDetail($seperator, 'Url', $this->checkedUrls);
        }
        if($withVersions){
            $text .= $this->getCheckedDetail($seperator, 'Version', $this->checkedVersions);
        }
        return $text;
    }

    /**
     * An administrative short description of the service
     * @return string
     */
    public function getDescription(): string
    {
        $description = 'Service "' . $this->getName() . '"';
        if($this->isPlugin){
            $description .= ', plugin "' . $this->pluginName . '"';
        }
        return $description;
    }

    /**
     * Helper to generate output for commands or debugging
     * @param string $msg
     * @param SymfonyStyle|null $io
     * @param string $ioMethod : can be 'note' | 'writeln' | 'success' | 'warning' | 'error' | 'caution'
     */
    public function output(string $msg, SymfonyStyle $io = null, string $ioMethod = 'note')
    {
        if (self::DO_DEBUG) {
            error_log('SERVICE ' . $ioMethod . ': ' . $msg);
        }
        if ($io) {
            $io->$ioMethod($msg);
        }
    }

    /**
     * Helper to add url & version for generating the description
     * @param string $url
     * @param string|null $version
     */
    protected function addCheckResult(string $url, string $version = null)
    {
        if (!in_array($url, $this->checkedUrls)) {
            $this->checkedUrls[] = $url;
            $this->checkedVersions[] = empty($version) ? 'unknown' : $version;
        }
    }

    /**
     * Helper to create message-details
     * @param string $seperator
     * @param string $title
     * @param array $items
     * @return string
     */
    protected function getCheckedDetail(string $seperator, string $title, array $items): string
    {
        if (count($items) > 1) {
            return $seperator . ' ' . $title . 's: ' . implode(', ', $items);
        }
        if (count($items) === 1) {
            return $seperator . ' ' . $title . ': ' . $items[0];
        }
        return '';
    }
}
