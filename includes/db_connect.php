<?php
// Database connection details will be read from environment variables on Render
$dbUrl = getenv('DATABASE_URL');

if ($dbUrl === false) {
    // Fallback for local development if .env files are not used
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "capstone1";
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

    // Dynamically increase max_allowed_packet for MySQL if needed and if possible
    if ($driver === 'mysql') {
        try {
            $stmt = $conn->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && intval($row['Value']) < 33554432) { // 32MB
                $conn->exec("SET GLOBAL max_allowed_packet = 33554432");
                // Reconnect to apply the new global configuration to this session
                $conn = null;
                $conn = new PDO($conn_str, $pdo_username, $pdo_password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } catch (PDOException $ex) {
            // Ignore if setting fails due to privileges or other issues
        }
    }
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

    // One record per submitted requirement. This avoids losing documents when
    // an application form has more than the two legacy image fields.
    if ($driver === 'pgsql') {
        $conn->exec("CREATE TABLE IF NOT EXISTS application_documents (
            id SERIAL PRIMARY KEY,
            application_id VARCHAR(255) NOT NULL,
            document_key VARCHAR(100) NOT NULL,
            document_label VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            document_data BYTEA NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_documents_application
                FOREIGN KEY (application_id) REFERENCES applications (id_number)
                ON DELETE CASCADE
        )");
    } else {
        $conn->exec("CREATE TABLE IF NOT EXISTS application_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id VARCHAR(255) NOT NULL,
            document_key VARCHAR(100) NOT NULL,
            document_label VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            document_data MEDIUMBLOB NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_application_documents_application (application_id),
            UNIQUE KEY uq_application_document (application_id, document_key),
            CONSTRAINT fk_documents_application
                FOREIGN KEY (application_id) REFERENCES applications (id_number)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Upgrade databases created before the complete document store was added.
    if ($driver === 'mysql') {
        try { $conn->exec('ALTER TABLE application_documents ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'); } catch (PDOException $e) {}
        try { $conn->exec('ALTER TABLE application_documents ADD UNIQUE KEY uq_application_document (application_id, document_key)'); } catch (PDOException $e) {}
    }

    // --- CPRAS System Enhancements Schema Migrations ---
    // Add columns to applications table if they do not exist
    $columns_to_add = [
        'sss_number'                 => "VARCHAR(50) DEFAULT NULL",
        'pension_amount'             => "DECIMAL(10,2) DEFAULT NULL",
        'date_of_death'              => "DATE DEFAULT NULL",
        'relationship_to_deceased'   => "VARCHAR(100) DEFAULT NULL",
        'is_proxy_application'       => "INT DEFAULT 0",
        'proxy_name'                 => "VARCHAR(255) DEFAULT NULL",
        'proxy_relationship'         => "VARCHAR(100) DEFAULT NULL",
        'proxy_contact_number'       => "VARCHAR(20) DEFAULT NULL",
        'proxy_token'                => "VARCHAR(255) DEFAULT NULL",
        'priority_level'             => "VARCHAR(20) DEFAULT 'normal'",
        'workflow_state'             => "VARCHAR(50) DEFAULT 'Received'",
        // Additional application information
        'email_address'              => "VARCHAR(255) DEFAULT NULL",
        'medical_conditions'         => "TEXT DEFAULT NULL",
        'birth_certificate_type'     => "VARCHAR(100) DEFAULT NULL",
        'medical_certificate_type'   => "VARCHAR(100) DEFAULT NULL",
        'client_identification_type' => "VARCHAR(100) DEFAULT NULL",
        'additional_notes'           => "TEXT DEFAULT NULL",
        // Dedicated return reason for document rejection tracking
        'return_reason'              => "VARCHAR(500) DEFAULT NULL",
        // OSCA official form fields
        'place_of_birth'             => "VARCHAR(255) DEFAULT NULL",
        'gender'                     => "VARCHAR(20) DEFAULT NULL",
        'civil_status'               => "VARCHAR(50) DEFAULT NULL",
        'mothers_maiden_name'        => "VARCHAR(255) DEFAULT NULL",
        'house_no'                   => "VARCHAR(50) DEFAULT NULL",
        'street'                     => "VARCHAR(255) DEFAULT NULL",
        'city'                       => "VARCHAR(100) DEFAULT 'Pasig City'",
        'province'                   => "VARCHAR(100) DEFAULT 'Metro Manila'",
        'zip_code'                   => "VARCHAR(10) DEFAULT NULL",
        'landmark'                   => "VARCHAR(255) DEFAULT NULL",
        'health_status'              => "VARCHAR(100) DEFAULT NULL",
        'senior_id_no'               => "VARCHAR(50) DEFAULT NULL",
        'id_purpose'                 => "VARCHAR(20) DEFAULT NULL",
        'milestone_age'              => "VARCHAR(10) DEFAULT NULL",
        'claimant_name'              => "VARCHAR(255) DEFAULT NULL",
        'claimant_relationship'      => "VARCHAR(100) DEFAULT NULL",
        'claimant_contact'           => "VARCHAR(20) DEFAULT NULL",
        'deceased_last_name'         => "VARCHAR(255) DEFAULT NULL",
        'deceased_first_name'        => "VARCHAR(255) DEFAULT NULL",
        'deceased_middle_name'       => "VARCHAR(255) DEFAULT NULL",
        'deceased_suffix'            => "VARCHAR(50) DEFAULT NULL",
        'deceased_birth_date'        => "DATE DEFAULT NULL",
        'landbank_card_no'           => "VARCHAR(50) DEFAULT NULL",
        'applicant_name'             => "VARCHAR(255) DEFAULT NULL",
        'visit_purpose'              => "VARCHAR(255) DEFAULT NULL",
        'living_arrangement'         => "VARCHAR(100) DEFAULT NULL",
        'is_pensioner'               => "TINYINT(1) DEFAULT NULL",
        'pension_source'             => "VARCHAR(255) DEFAULT NULL",
        'family_support'             => "TINYINT(1) DEFAULT NULL",
        'family_support_amount'      => "DECIMAL(10,2) DEFAULT NULL",
        'personal_income'            => "TINYINT(1) DEFAULT NULL",
        'personal_income_amount'     => "DECIMAL(10,2) DEFAULT NULL",
        'health_condition'           => "VARCHAR(255) DEFAULT NULL",
        'with_maintenance'           => "TINYINT(1) DEFAULT NULL",
        'maintenance_spec'           => "VARCHAR(255) DEFAULT NULL",
        'visit_summary'              => "TEXT DEFAULT NULL",
        'name_on_card'               => "VARCHAR(23) DEFAULT NULL",
        'tin'                        => "VARCHAR(50) DEFAULT NULL",
        'id_type_presented'          => "VARCHAR(100) DEFAULT NULL",
        'nationality'                => "VARCHAR(50) DEFAULT 'Filipino'",
        'source_of_funds'            => "VARCHAR(255) DEFAULT NULL",
        'atm_card_no'                => "VARCHAR(50) DEFAULT NULL",
        'control_no'                 => "VARCHAR(50) DEFAULT NULL",
        'is_permanent_income'        => "TINYINT(1) DEFAULT NULL",
        'income_source'              => "VARCHAR(255) DEFAULT NULL",
        'owns_house'                 => "TINYINT(1) DEFAULT NULL",
        'is_renter'                  => "TINYINT(1) DEFAULT NULL",
        'psa_birth_cert'             => "VARCHAR(255) DEFAULT NULL",
        'barangay_residency'         => "VARCHAR(255) DEFAULT NULL",
        'comelec_cert'               => "VARCHAR(255) DEFAULT NULL",
        'proof_of_life'              => "VARCHAR(255) DEFAULT NULL",
        'auth_letter'                => "VARCHAR(255) DEFAULT NULL",
        'proxy_id'                   => "VARCHAR(255) DEFAULT NULL",
        'proxy_birth_cert'           => "VARCHAR(255) DEFAULT NULL",
        'home_visitation_form'       => "VARCHAR(255) DEFAULT NULL",
        'return_reason'              => "VARCHAR(500) DEFAULT NULL",
        // OSCA official form fields
        'place_of_birth'             => "VARCHAR(255) DEFAULT NULL",
        'gender'                     => "VARCHAR(20) DEFAULT NULL",
        'civil_status'               => "VARCHAR(50) DEFAULT NULL",
        'mothers_maiden_name'        => "VARCHAR(255) DEFAULT NULL",
        'house_no'                   => "VARCHAR(50) DEFAULT NULL",
        'street'                     => "VARCHAR(255) DEFAULT NULL",
        'city'                       => "VARCHAR(100) DEFAULT 'Pasig City'",
        'province'                   => "VARCHAR(100) DEFAULT 'Metro Manila'",
        'zip_code'                   => "VARCHAR(10) DEFAULT NULL",
        'landmark'                   => "VARCHAR(255) DEFAULT NULL",
        'health_status'              => "VARCHAR(100) DEFAULT NULL",
        'senior_id_no'               => "VARCHAR(50) DEFAULT NULL",
        'id_purpose'                 => "VARCHAR(20) DEFAULT NULL",
        'milestone_age'              => "VARCHAR(10) DEFAULT NULL",
        'claimant_name'              => "VARCHAR(255) DEFAULT NULL",
        'claimant_relationship'      => "VARCHAR(100) DEFAULT NULL",
        'claimant_contact'           => "VARCHAR(20) DEFAULT NULL",
        'deceased_last_name'         => "VARCHAR(255) DEFAULT NULL",
        'deceased_first_name'        => "VARCHAR(255) DEFAULT NULL",
        'deceased_middle_name'       => "VARCHAR(255) DEFAULT NULL",
        'deceased_suffix'            => "VARCHAR(50) DEFAULT NULL",
        'deceased_birth_date'        => "DATE DEFAULT NULL",
        'landbank_card_no'           => "VARCHAR(50) DEFAULT NULL",
        'applicant_name'             => "VARCHAR(255) DEFAULT NULL",
        'visit_purpose'              => "VARCHAR(255) DEFAULT NULL",
        'living_arrangement'         => "VARCHAR(100) DEFAULT NULL",
        'is_pensioner'               => "TINYINT(1) DEFAULT NULL",
        'pension_source'             => "VARCHAR(255) DEFAULT NULL",
        'family_support'             => "TINYINT(1) DEFAULT NULL",
        'family_support_amount'      => "DECIMAL(10,2) DEFAULT NULL",
        'personal_income'            => "TINYINT(1) DEFAULT NULL",
        'personal_income_amount'     => "DECIMAL(10,2) DEFAULT NULL",
        'health_condition'           => "VARCHAR(255) DEFAULT NULL",
        'with_maintenance'           => "TINYINT(1) DEFAULT NULL",
        'maintenance_spec'           => "VARCHAR(255) DEFAULT NULL",
        'visit_summary'              => "TEXT DEFAULT NULL",
        'name_on_card'               => "VARCHAR(23) DEFAULT NULL",
        'tin'                        => "VARCHAR(50) DEFAULT NULL",
        'id_type_presented'          => "VARCHAR(100) DEFAULT NULL",
        'nationality'                => "VARCHAR(50) DEFAULT 'Filipino'",
        'source_of_funds'            => "VARCHAR(255) DEFAULT NULL",
        'atm_card_no'                => "VARCHAR(50) DEFAULT NULL",
        'control_no'                 => "VARCHAR(50) DEFAULT NULL",
        'is_permanent_income'        => "TINYINT(1) DEFAULT NULL",
        'income_source'              => "VARCHAR(255) DEFAULT NULL",
        'owns_house'                 => "TINYINT(1) DEFAULT NULL",
        'is_renter'                  => "TINYINT(1) DEFAULT NULL",
        'psa_birth_cert'             => "VARCHAR(255) DEFAULT NULL",
        'barangay_residency'         => "VARCHAR(255) DEFAULT NULL",
        'comelec_cert'               => "VARCHAR(255) DEFAULT NULL",
        'proof_of_life'              => "VARCHAR(255) DEFAULT NULL",
        'auth_letter'                => "VARCHAR(255) DEFAULT NULL",
        'proxy_id'                   => "VARCHAR(255) DEFAULT NULL",
        'proxy_birth_cert'           => "VARCHAR(255) DEFAULT NULL",
        'home_visitation_form'       => "VARCHAR(255) DEFAULT NULL",
        'landbank_enrollment_form'   => "VARCHAR(255) DEFAULT NULL",
        'parent_senior_id'           => "VARCHAR(50) DEFAULT NULL",
        'home_visit_scheduled_at'    => ($driver === 'pgsql' ? "TIMESTAMP DEFAULT NULL" : "DATETIME DEFAULT NULL"),
        'home_visit_status'          => "VARCHAR(30) DEFAULT NULL",
        'sms_notification_status'    => "VARCHAR(30) DEFAULT NULL",
        'is_archived'                => "TINYINT(1) DEFAULT 0",
        'archived_at'                => ($driver === 'pgsql' ? "TIMESTAMP DEFAULT NULL" : "DATETIME DEFAULT NULL"),
        'archived_by'                => "VARCHAR(100) DEFAULT NULL",
    ];

    foreach ($columns_to_add as $column => $definition) {
        try {
            $conn->exec("ALTER TABLE applications ADD COLUMN $column $definition");
        } catch (PDOException $e) {
            // Column likely already exists, ignore
        }
    }

    // Add archive columns to users table
    $user_columns_to_add = [
        'is_archived'  => "TINYINT(1) DEFAULT 0",
        'archived_at'  => ($driver === 'pgsql' ? "TIMESTAMP DEFAULT NULL" : "DATETIME DEFAULT NULL"),
        'archived_by'  => "VARCHAR(100) DEFAULT NULL",
    ];
    foreach ($user_columns_to_add as $column => $definition) {
        try {
            $conn->exec("ALTER TABLE users ADD COLUMN $column $definition");
        } catch (PDOException $e) {
            // Column likely already exists, ignore
        }
    }

    // Modify application_type column type based on DB driver
    if ($driver === 'mysql') {
        try {
            $conn->exec("ALTER TABLE applications MODIFY COLUMN application_type VARCHAR(50) NOT NULL");
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

    // Create application_history table for workflow audit tracking
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

    // Create audit_trail table for system activity tracking
    if ($driver === 'pgsql') {
        $conn->exec("CREATE TABLE IF NOT EXISTS audit_trail (
            id SERIAL PRIMARY KEY,
            user_id INT DEFAULT NULL,
            username VARCHAR(100) NOT NULL,
            role VARCHAR(50) NOT NULL,
            barangay VARCHAR(100) DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $conn->exec("CREATE TABLE IF NOT EXISTS audit_trail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            username VARCHAR(100) NOT NULL,
            role VARCHAR(50) NOT NULL,
            barangay VARCHAR(100) DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_user (user_id),
            INDEX idx_audit_action (action),
            INDEX idx_audit_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Durable SMS outbox. Messages are retained when an SMS provider is not
    // configured or temporarily unavailable, so a failed text never blocks
    // the underlying application submission.
    if ($driver === 'pgsql') {
        $conn->exec("CREATE TABLE IF NOT EXISTS sms_notifications (
            id SERIAL PRIMARY KEY,
            application_id VARCHAR(255) NOT NULL,
            recipient VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'queued',
            provider_reference VARCHAR(255) DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP DEFAULT NULL
        )");
    } else {
        $conn->exec("CREATE TABLE IF NOT EXISTS sms_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id VARCHAR(255) NOT NULL,
            recipient VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'queued',
            provider_reference VARCHAR(255) DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_sms_application (application_id),
            INDEX idx_sms_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

} catch(PDOException $e) {
    // Instead of echoing, re-throw the exception or handle it silently for an API.
    // The calling script will catch this PDOException and return a JSON error.
    throw $e;
}
?>
