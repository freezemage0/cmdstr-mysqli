<?php

    namespace mysqli;

    use mysqli;
    use mysqli_result;
    use mysqli_stmt;

    /**
     * A wrapper that simplifies execute MySQL queries
     * 
     * ```php
     * // example usage
     * $conn = db::createConnection("users", "admin", "password", "localhost");
     * $db = new db("SELECT username FROM users WHERE username LIKE ?", $conn);
     * $result = $db->execute("user%")->get_result();
     * 
     * while ($row = $result->fetch_assoc()) var_dump($row);
     * ```
     * 
     * @property mysqli $conn
     * @property mysqli_stmt $stmt
     * @property string $query
     * @property array $parameters
     * @property int $stage
     * @method createConnection
     * @method bind_param
     * @method execute
     * @method get_result
     * @method json_encode
     */
    class db {
        /**
         * @property mysqli
         */
        private $conn;

        /**
         * @property mysqli_stmt
         */
        private $stmt;

        /**
         * @property string
         */
        private $query;

        /**
         * @property array
         */
        private $parameters;

        /**
         * @property int 
         */
        private $stage = 0;
        
        /**
         * Holds database connection credentials
         * 
         * @property array $dbc
         * 
         * ```php
         * $dbc = [
         *    "db" => [
         *       "host" => "localhost",
         *       "database" => "db",
         *       "username" => "root",
         *       "password" => "password"
         *    ]
         *    // ... other connections can be included below here
         * ];
         * ```
         */
        private static $dbc = [
            "db" => [
                "host" => "localhost",
                "database" => "db",
                "username" => "root",
                "password" => "password"
            ]
        ];

        /**
         * Used for creation a connection to a database
         * 
         * @param string $database
         * @param string $username [optional]
         * @param string $password [optional]
         * @param string $host [optional]
         * 
         * Example Usage
         * ```php
         * // if you want to use predefined credentials in $dbc
         * $conn = db::createConnection("some_cool_database");
         * 
         * // or if you want to insert your own credentials
         * $conn = db::createConnection("database", "username", "password", "host");
         * // ...
         * ```
         * 
         * @return mysqli
         */
        public static function createConnection(string $database, string $username="", string $password="", string $host=""):mysqli {
            if (empty($username) || empty($password) || empty($host)) {
                if (!isset(self::$dbc[$database])) trigger_error("Database $database doesn't exist in configuration ", E_USER_ERROR);
                $c = self::$dbc[$database];
                $conn = mysqli_connect($c['host'], $c['username'], $c['password'], $c['database']);
            } else $conn = mysqli_connect($host, $username, $password, $database);

            if (!$conn) trigger_error("Failed to connect to database", E_USER_ERROR);

            return $conn;
        }

        /**
         * @param string $query
         * @param mysqli $conn
         * @param int|string|float|bool ...$parameters
         * 
         * @return db
         */
        public function __construct(string $query, mysqli $conn, int|string|float|bool ...$parameters) {
            $this->stage = 1;

            if (isset($this->stmt)) $this->stmt->close();

            if (!empty($parameters)) $this->bind_param(...$parameters);

            $this->query = $query;
            $this->conn = $conn;

            return $this;
        }

        /**
         * Saves parameters before sql execution
         * 
         * **NOTE: Booleans are converted to `1` if true or `0` if false**
         * 
         * @param int|string|float|bool ...$parameters
         * 
         * @return db
         */
        public function bind_param(int|string|float|bool ...$parameters):db {
            if ($this->stage > 2) trigger_error("bind_param method used out of order", E_USER_ERROR);

            $types = "";
            
            foreach ($parameters as $key => $p) {
                if (is_string($p)) $types.= "s";
                if (is_int($p)) $types.= "i";
                if (is_double($p)) $types.= "d";

                if (is_bool($p)) {
                    $parameters[$key] = ($p) ? 1 : 0;
                    $types .= "i";
                }
            }

            $this->parameters['types'] = $types;
            $this->parameters['parameters'] = $parameters;
            
            $this->stage = 2;

            return $this;
        }

        /**
         * Executes MySQL query
         * 
         * @param int|string|float|bool ...$parameters
         * 
         * @return db
         */
        public function execute(int|string|float|bool ...$parameters):db {
            if ($this->stage < 3 && $this->stage > 4) trigger_error("Execute method used out of order!", E_USER_ERROR);

            $this->stmt = $this->conn->prepare($this->query);
            
            if (!empty($parameters)) $this->bind_param(...$parameters);

            if (!empty($this->parameters) && isset($this->parameters['types']) && $this->parameters['parameters']) $this->stmt->bind_param($this->parameters['types'], ...$this->parameters['parameters']);

            if (!$this->stmt->execute()) trigger_error("Failed to execute query: {$this->query}", E_USER_ERROR);

            $this->stage = 3;

            return $this;
        }

        /**
         * Json Encode Rows
         * 
         * @param array|int $flags
         * @param int $depth
         * 
         * @return array|string
         */
        public function json_encode(array|int $flags=0, int $depth=512):string|object {
            if ($this->stage == 2) $this->execute();
            if ($this->stage < 3 || $this->stage > 3) trigger_error("rows_json_encode method used out of order.", E_USER_ERROR);

            $rows = array();

            $result = $this->stmt->get_result();
            while ($row = $result->fetch_assoc()) $rows[] = $row;

            $this->stage = 4;

            return json_encode($rows, $flags, $depth);
        }

        /**
         * Fetch result
         * 
         * @return mysqli_result
         */
        public function get_result():mysqli_result {
            if ($this->stage == 2) $this->execute();
            if ($this->stage < 3 || $this->stage > 3) trigger_error("get_result method used out of order.", E_USER_ERROR);

            $this->stage = 4;

            return $this->stmt->get_result();
        }
    }

?>
