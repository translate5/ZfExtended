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

declare(strict_types=1);

namespace MittagQI\ZfExtended\Csp;

use Zend_Config;

class FrameSourceResolver
{
    private const array URL_CONFIGS = [
        'runtimeOptions.editor.customPanel.url',
        'runtimeOptions.frontend.helpWindow.customeroverview.loaderUrl',
        'runtimeOptions.frontend.helpWindow.editor.loaderUrl',
        'runtimeOptions.frontend.helpWindow.instanttranslate.loaderUrl',
        'runtimeOptions.frontend.helpWindow.languageresource.loaderUrl',
        'runtimeOptions.frontend.helpWindow.preferences.loaderUrl',
        'runtimeOptions.frontend.helpWindow.project.loaderUrl',
        'runtimeOptions.frontend.helpWindow.taskoverview.loaderUrl',
        'runtimeOptions.frontend.helpWindow.termportal.loaderUrl',
    ];

    private readonly string $configuredHost;

    public function __construct(
        private readonly Zend_Config $config,
        string $defaultHost,
        private readonly string $defaultScheme = 'http',
    ) {
        $host = $this->config->runtimeOptions->server->name ?? null;
        $this->configuredHost = $this->normalizeHost(is_string($host) && $host !== '' ? $host : $defaultHost);
    }

    public function getSources(): array
    {
        $sources = [];

        foreach (self::URL_CONFIGS as $configPath) {
            $origin = $this->extractExternalOriginFromConfig($configPath);
            if ($origin !== null) {
                $sources[$origin] = $origin;
            }
        }

        return array_values($sources);
    }

    public function extractExternalOrigin(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $parts = parse_url($this->defaultScheme . ':' . $url);
        } else {
            $parts = parse_url($url);
        }

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? $this->defaultScheme);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $origin = $scheme . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $this->isSameOriginHost($parts['host']) ? null : $origin;
    }

    private function extractExternalOriginFromConfig(string $configPath): ?string
    {
        $url = $this->getConfigValueByPath($configPath);
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        return $this->extractExternalOrigin(trim($url));
    }

    private function getConfigValueByPath(string $configPath): mixed
    {
        $value = $this->config;

        foreach (explode('.', $configPath) as $segment) {
            if (! $value instanceof Zend_Config || ! isset($value->$segment)) {
                return null;
            }

            $value = $value->$segment;
        }

        return $value;
    }

    private function isSameOriginHost(string $host): bool
    {
        return strtolower($host) === strtolower($this->configuredHost);
    }

    private function normalizeHost(string $host): string
    {
        return preg_replace('/:\d+$/', '', $host) ?: 'localhost';
    }
}
