<?php
class ZfExtended_Models_Installer_DbConfig {

    /**
     * Hostname
     *
     * @var string
     */
    public string $host;

    /**
     * Username
     *
     * @var string
     */
    public string $username;

    /**
     * Password
     *
     * @var string
     */
    public string $password;

    /**
     * Port number
     *
     * @var int
     */
    public int $port;

    /**
     * Database name
     *
     * @var string
     */
    public string $dbname;

    /**
     * Initialise from array
     *
     * @param array $params
     * @return $this
     */
    public function initFromArray(array $params) : ZfExtended_Models_Installer_DbConfig {

        // Setup params
        foreach (['host', 'username', 'password', 'port', 'dbname'] as $param) {
            if (isset($params[$param])) {
                $this->$param = $params[$param];
            }
        }

        // Return instance itself
        return $this;
    }

    /**
     * Prepare and return string to be used as $dsn argument for new PDO() call
     *
     * @see https://www.php.net/manual/en/pdo.construct.php
     */
    public function toPdoString() : string {
        $pdo = 'mysql:host=' . $this->host;
        if (isset($this->port)) $pdo .= ';port=' . $this->port;
        if (isset($this->dbname)) $pdo .= ';dbname=' . $this->dbname;
        return $pdo;
    }
}
