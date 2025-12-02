
<?php
/**
 * Naseej Admin - Database Configuration
 * Handles MySQL database connection using PDO
 */

class Database {
    // Database credentials - UPDATE THESE FOR YOUR HOSTING
    private $host = "";
    private $port = "";
    private $db_name = "";
    private $username = "";
    private $password = "";  // UPDATE THIS
    private $conn;

    /**
     * Get database connection
     * @return PDO Database connection object
     */
    public function connect(): PDO {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Set error mode to exceptions
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Set default fetch mode to associative array
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Emulate prepares for better compatibility
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch(PDOException $e) {
            // Log error (in production, log to file instead of displaying)
            error_log("Database Connection Error: " . $e->getMessage());
            die(json_encode([
                "status" => "error",
                "message" => "Database connection failed. Please try again later."
            ]));
        }
        
        return $this->conn;
    }

    /**
     * Close database connection
     */
    public function disconnect() {
        $this->conn = null;
    }

    /**
     * Get last inserted ID
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollBack();
    }
}
?>