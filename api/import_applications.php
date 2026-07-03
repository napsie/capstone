<?php
session_start();
require_once '../includes/db_connect.php';

$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");

    if ($handle === FALSE) {
        $message = "<p class='error'>Failed to open CSV file.</p>";
    } else {
        // Get the header row
        $header = fgetcsv($handle);

        // Define expected CSV headers and their corresponding database columns
        // Mark critical columns that are NOT NULL in the database
        $csv_to_db_map = [
            'Email Address' => ['db_column' => 'email_address', 'critical' => false],
            'Full Name(LN, FN MN.)' => ['db_column' => 'full_name', 'critical' => true],
            'Application Type' => ['db_column' => 'application_type', 'critical' => true],
            'Birthdate' => ['db_column' => 'birth_date', 'critical' => true],
            'Contact Number' => ['db_column' => 'contact_number', 'critical' => true],
            'Complete Address' => ['db_column' => 'complete_address', 'critical' => true],
            'Emergency Contact Person' => ['db_column' => 'emergency_contact_name', 'critical' => false],
            'Emergency Contact Number' => ['db_column' => 'emergency_contact', 'critical' => true],
            'Medical Conditions (Optional)' => ['db_column' => 'medical_conditions', 'critical' => false],
            'Birth Certificate' => ['db_column' => 'birth_certificate_type', 'critical' => false],
            'Medical Certificate' => ['db_column' => 'medical_certificate_type', 'critical' => false],
            'Client ID' => ['db_column' => 'client_identification_type', 'critical' => false],
            'Proof of Address' => ['db_column' => 'proof_of_address_type', 'critical' => false],
            'Updated ID Image(1x1)' => ['db_column' => 'id_image_type', 'critical' => false],
            'Additional Notes' => ['db_column' => 'additional_notes', 'critical' => false]
            // 'Barangay' is now taken from session, not CSV
        ];

        // Find the column indexes based on the CSV header
        $column_indexes = [];
        $missing_critical_headers = [];
        foreach ($csv_to_db_map as $csv_header => $map_details) {
            $db_column = $map_details['db_column'];
            $critical = $map_details['critical'];
            $index = array_search($csv_header, $header);
            if ($index !== false) {
                $column_indexes[$db_column] = $index;
            } else {
                $column_indexes[$db_column] = null; // Column not found
                if ($critical) {
                    $missing_critical_headers[] = $csv_header;
                }
            }
        }

        if (!empty($missing_critical_headers)) {
            $message = "<p class='error'>Error: Missing critical CSV headers: " . implode(', ', $missing_critical_headers) . ". Please ensure your CSV file contains these columns.</p>";
            fclose($handle);
        } else {
            // Construct the SQL INSERT statement dynamically
            $db_columns_in_order = [];
            $placeholders = [];
            
            foreach ($csv_to_db_map as $map_details) {
                $db_columns_in_order[] = $map_details['db_column'];
                $placeholders[] = '?';
            }

            // Add fixed values for status, date_submitted, and barangay from session
            $db_columns_in_order[] = 'status';
            $placeholders[] = '?';
            $db_columns_in_order[] = 'date_submitted';
            $placeholders[] = '?';
            $db_columns_in_order[] = 'barangay';
            $placeholders[] = '?';

            $sql = "INSERT INTO applications (" . implode(', ', $db_columns_in_order) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $conn->prepare($sql);

            $imported_count = 0;
            $row_number = 1; // Start from 1 for data rows after header
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_number++;
                $execute_params = [];
                $skip_row = false;

                foreach ($csv_to_db_map as $csv_header => $map_details) {
                    $db_column = $map_details['db_column'];
                    $critical = $map_details['critical'];
                    $index = $column_indexes[$db_column];

                    $value = ($index !== null && isset($data[$index])) ? trim($data[$index]) : null;

                    // Handle critical fields that are empty
                    if ($critical && ($value === null || $value === '')) {
                        $errors[] = "Row {$row_number}: Critical field '{$csv_header}' (maps to DB column '{$db_column}') is empty or missing. Skipping row.";
                        $skip_row = true;
                        break; 
                    }
                    
                    // For non-critical fields, convert null to empty string if needed by DB schema
                    if ($value === null) {
                        $value = ''; 
                    }
                    $execute_params[] = $value;
                }

                if ($skip_row) {
                    continue; // Skip to the next row in CSV
                }
                
                // Add fixed values for status, date_submitted, and barangay from session
                $execute_params[] = 'pending';
                $execute_params[] = date('Y-m-d H:i:s'); // Current timestamp
                $execute_params[] = $_SESSION['barangay']; // Barangay from session

                try {
                    $stmt->execute($execute_params);
                    $imported_count++;
                } catch (PDOException $e) {
                    $errors[] = "Row {$row_number}: Database error: " . $e->getMessage() . "\nSQL: " . $sql . "\nParams: " . print_r($execute_params, true);
                }
            }

            fclose($handle);
            if ($imported_count > 0) {
                $message .= "<p class='success'>Successfully imported {$imported_count} applications.</p>";
            }
            if (!empty($errors)) {
                $message .= "<p class='error'>Errors encountered during import:</p><ul>";
                foreach ($errors as $error) {
                    $message .= "<li>" . htmlspecialchars($error) . "</li>";
                }
                $message .= "</ul>";
            }
            if ($imported_count == 0 && empty($errors)) {
                $message = "<p class='warning'>No applications were imported. Please check your CSV file and ensure it contains data.</p>";
            }
        }
    }
}

$_SESSION['import_message'] = $message;
header('Location: ../pages/submit_application.php');
exit;
?>