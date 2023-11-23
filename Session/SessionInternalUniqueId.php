<?php

namespace MittagQI\ZfExtended\Session;

use Exception;

/**
 *  This class is responsible for generating, setting, and retrieving a unique public internal session ID.
 *  The unique session ID is stored as a public constant `INTERNAL_SESSION_UNIQUE_ID`.
 *  This constant is used for internal representation of the session ID.
 */
class SessionInternalUniqueId
{

    public const INTERNAL_SESSION_UNIQUE_ID = 'INTERNAL_SESSION_UNIQUE_ID';

    private static ?SessionInternalUniqueId $instance = null;

    public static function getInstance(): SessionInternalUniqueId
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate uniqie hash and store it in a constant.
     * @throws Exception
     */
    public function generate(): string
    {
        if (!defined(self::INTERNAL_SESSION_UNIQUE_ID)) {
            define(
                self::INTERNAL_SESSION_UNIQUE_ID,
                bin2hex(random_bytes(16))
            );
        }
        return constant(self::INTERNAL_SESSION_UNIQUE_ID);
    }

    public function set(string $value): void
    {
        if (!defined(self::INTERNAL_SESSION_UNIQUE_ID)) {
            define(self::INTERNAL_SESSION_UNIQUE_ID, $value);
        }
    }

    public function get(): ?string
    {
        if (defined(self::INTERNAL_SESSION_UNIQUE_ID)) {
            return constant(self::INTERNAL_SESSION_UNIQUE_ID);
        }
        return null;
    }

}