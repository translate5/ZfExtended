<?php

declare(strict_types=1);

namespace MittagQI\ZfExtended\Session;

use Exception;

/**
 *  This class is responsible for generating, setting, and retrieving a unique public internal session ID.
 *  The unique session ID is stored as a public constant `INTERNAL_SESSION_UNIQUE_ID`.
 *  This constant is used for internal representation of the session ID.
 */
class SessionInternalUniqueId
{

    private string $internalId;

    private static ?SessionInternalUniqueId $instance = null;

    public static function getInstance(): SessionInternalUniqueId
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->generate();
        }
        return self::$instance;
    }

    /**
     * Generate uniqie hash and store it in a constant.
     * @throws Exception
     */
    private function generate(): string
    {
        return $this->internalId = bin2hex(random_bytes(16));
    }

    public function set(?string $value): void
    {
        if (is_null($value)) {
            $this->generate();
        }
        $this->internalId = $value;
    }

    public function get(): ?string
    {
        return $this->internalId;
    }

}