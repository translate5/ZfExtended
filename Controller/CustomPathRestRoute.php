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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 */
/**
 * Wrapper Class for using additional, normal routes, which fake REST-Routes.
 * This is needed since Rest Routes just know and call GET PUT POST DELETE actions.
 * All other additionally actions must configured as either RestFakeRoute or RestLikeRoute
 *
 * RestFakeRoute and RestLikeRoute Routes are treated in error and access handling like a a rest route (called via API)
 *
 * Difference between RestFakeRoute and RestLikeRoute:
 * RestFakeRoute: No automatic request result conversion is done - the result is outputted as generated (HTML, binary data from a file, JSON manually encoded)
 * RestLikeRoute: REST_Controller_Plugin_RestHandler is invoked, so the data in the view is returned in the format as requested by the caller  (mainly JSON)
 */
class ZfExtended_Controller_CustomPathRestRoute extends Zend_Rest_Route
{
    private readonly string $pattern;

    private array $pathParams = [];

    public function __construct(
        Zend_Controller_Front $front,
        private string $route,
        private array $settings,
    ) {
        parent::__construct($front, [], [
            $settings['module'] => [$settings['controller']],
        ]);
        $this->route = trim($route, self::URI_DELIMITER);

        preg_match_all('/:(\w+)/', $this->route, $pathParams);

        $this->pathParams = $pathParams[1];

        if (in_array('id', $this->pathParams, true)) {
            throw new InvalidArgumentException('The route pattern cannot contain the ":id" parameter');
        }

        $this->pathParams[] = 'id';

        $allowedIdentifierChars = '[{}\-_0-9a-zA-Z]';

        // Convert the route pattern into a regular expression
        $pattern = preg_replace_callback(
            '/:\w+/',
            fn ($matches) => '(?<' . str_replace(':', '', $matches[0]) . ">$allowedIdentifierChars+)",
            $route
        );

        $this->pattern = "#^$pattern(?<id>/$allowedIdentifierChars+)?$#";
    }

    public function match($request, $partial = false)
    {
        if (! $request instanceof Zend_Controller_Request_Http) {
            $request = $this->_front->getRequest();
        }

        $path = $request->getPathInfo();

        if (! preg_match($this->pattern, trim(urldecode($path), self::URI_DELIMITER), $matches)) {
            return false;
        }

        foreach ($this->pathParams as $key) {
            $request->setParam($key, isset($matches[$key]) ? trim($matches[$key], self::URI_DELIMITER) : null);
        }

        // fake path to use controller provided in defaults only
        $fakePath = $this->settings['module'] . self::URI_DELIMITER . $this->settings['controller'];

        if (null !== $request->getParam('id')) {
            $fakePath .= self::URI_DELIMITER . $request->getParam('id');
        }

        $request->setPathInfo($fakePath);

        $result = parent::match($request, $partial);

        $this->setMatchedPath($path);

        return $result;
    }
}
