<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5

 Copyright (c) 2013 - 2022 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 translate5 plug-ins that are distributed under GNU AFFERO GENERAL PUBLIC LICENSE version 3:
 Please see http://www.translate5.net/plugin-exception.txt or plugin-exception.txt in the root
 folder of translate5.

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
             http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

class ZfExtended_Auth_Token_Token
{

    public const DEFAULT_TOKEN_DESCRIPTION = 'Default';

    public const TOKEN_SEPARATOR = ':';

    private string $token;

    private ?int $prefix;

    /**
     * @param string $token application token provided on authentication
     */
    public function __construct(string $token)
    {
        $tokenParts = explode(self::TOKEN_SEPARATOR,$token);

        if( empty($tokenParts) || count($tokenParts) !== 2){
            return;
        }
        $this->token = $tokenParts[1] ?? '';
        // validate the site of the token
        if(!preg_match('/[a-zA-Z0-9]{32}/', $this->token)){
            $this->token = '';
            $this->prefix = null;
            return;
        }
        $this->prefix = $tokenParts[0] ?? null;
    }

    /***
     * @param string $prefix
     * @return string
     * @throws Exception
     */
    public static function generateAuthToken(string $prefix): string
    {
        return $prefix . self::TOKEN_SEPARATOR . bin2hex(random_bytes(16));
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return int|null
     */
    public function getPrefix(): ?int
    {
        return $this->prefix;
    }
}