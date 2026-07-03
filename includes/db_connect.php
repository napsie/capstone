<?php
// Database connection details will be read from environment variables on Render
$dbUrl = getenv('DATABASE_URL');

if ($dbUrl === false) {
    // Fallback for local development if .env files are not used
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "carelink_db";
    $conn_str = "mysql:host=$servername;dbname=$dbname";
    $pdo_username = $username;
    $pdo_password = $password;
} else {
    // Parse the DATABASE_URL from Render
    $dbopts = parse_url($dbUrl);
    $servername = $dbopts["host"];
    $dbname = ltrim($dbopts["path"], '/');
    $pdo_username = $dbopts["user"];
    $pdo_password = $dbopts["pass"];
    $port = $dbopts["port"];
    // The driver is pgsql for PostgreSQL on Render
    $conn_str = "pgsql:host=$servername;port=$port;dbname=$dbname";
}


try {
    $conn = new PDO($conn_str, $pdo_username, $pdo_password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create system_settings table if it doesn't exist and insert default values
    // Note: AUTO_INCREMENT is `SERIAL` in PostgreSQL
    $conn->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY DEFAULT 1,
            session_timeout INT NOT NULL DEFAULT 30,
            max_login_attempts INT NOT NULL DEFAULT 5,
            backup_frequency VARCHAR(50) NOT NULL DEFAULT 'weekly',
            auto_backup BOOLEAN NOT NULL DEFAULT TRUE
        );
    ");
    
    // Check the driver and use the appropriate INSERT syntax
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        // Use ON CONFLICT DO NOTHING for PostgreSQL to avoid inserting duplicates
        $conn->exec("
            INSERT INTO system_settings (id, session_timeout, max_login_attempts, backup_frequency, auto_backup)
            VALUES (1, 30, 5, 'weekly', TRUE)
            ON CONFLICT (id) DO NOTHING;
        ");
    } else {
        // Use INSERT IGNORE for MySQL/MariaDB to avoid inserting duplicates
        $conn->exec("
            INSERT IGNORE INTO system_settings (id, session_timeout, max_login_attempts, backup_frequency, auto_backup)
            VALUES (1, 30, 5, 'weekly', TRUE);
        ");
    }

    // --- CPRAS System Enhancements Schema Migrations ---
    // Add columns to applications table if they do not exist
    $columns_to_add = [
        'sss_number' => "VARCHAR(50) DEFAULT NULL",
        'pension_amount' => "DECIMAL(10,2) DEFAULT NULL",
        'date_of_death' => "DATE DEFAULT NULL",
        'relationship_to_deceased' => "VARCHAR(100) DEFAULT NULL",
        'is_proxy_application' => "INT DEFAULT 0",
        'proxy_name' => "VARCHAR(255) DEFAULT NULL",
        'proxy_relationship' => "VARCHAR(100) DEFAULT NULL",
        'proxy_token' => "VARCHAR(255) DEFAULT NULL",
        'priority_level' => "VARCHAR(20) DEFAULT 'normal'",
        'workflow_state' => "VARCHAR(50) DEFAULT 'Received'"
    ];

    foreach ($columns_to_add as $column => $definition) {
        try {
            $conn->exec("ALTER TABLE applications ADD COLUMN $column $definition");
        } catch (PDOException $e) {
            // Column likely already exists, ignore
        }
    }

    // Modify application_type column type based on DB driver
    if ($driver === 'mysql') {
        try {
            $conn->exec("ALTER TABLE applications MODIFY COLUMN application_type ENUM('pwd', 'senior', 'pension', 'burial') NOT NULL");
        } catch (PDOException $e) {
            // Ignore if modification fails
        }
    } else {
        try {
            $conn->exec("ALTER TABLE applications ALTER COLUMN application_type TYPE VARCHAR(50)");
        } catch (PDOException $e) {
            // Ignore
        }
    }

    // Create application_history table for FSM tracking
    if ($driver === 'pgsql') {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS application_history (
                id SERIAL PRIMARY KEY,
                application_id VARCHAR(255) NOT NULL,
                previous_state VARCHAR(50) NOT NULL,
                new_state VARCHAR(50) NOT NULL,
                changed_by VARCHAR(100) NOT NULL,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                comments TEXT DEFAULT NULL
            );
        ");
    } else {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS application_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id VARCHAR(255) NOT NULL,
                previous_state VARCHAR(50) NOT NULL,
                new_state VARCHAR(50) NOT NULL,
                changed_by VARCHAR(100) NOT NULL,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                comments TEXT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

} catch(PDOException $e) {
    // Instead of echoing, re-throw the exception or handle it silently for an API.
    // The calling script will catch this PDOException and return a JSON error.
    throw $e;
}
?>