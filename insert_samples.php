<?php
$pdo = new PDO('mysql:host=localhost;dbname=capstone1', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Generate unique IDs
    $id1 = 'SENIOR-2026-' . strtoupper(substr(md5(uniqid()), 0, 6));
    $id2 = 'SENIOR-2026-' . strtoupper(substr(md5(uniqid()), 0, 6));

    $sql = "INSERT INTO applications (
        id_number, full_name, application_type, lastName, firstName, middleName, birth_date, 
        contact_number, complete_address, emergency_contact, emergency_contact_name, 
        barangay, status, workflow_state, priority_level, gender, civil_status,
        place_of_birth, nationality
    ) VALUES 
    (
        :id1, 'Renato Santos Villanueva', 'Senior Citizen', 'Villanueva', 'Renato', 'Santos', '1956-03-12',
        '09175584321', 'Blk 5 Lot 18 Emerald St., Pinagbuhatan, Pasig City', '09178823456', 'Elena Villanueva',
        'Pinagbuhatan', 'pending', 'Received', 'normal', 'Male', 'Married',
        'Pasig City', 'Filipino'
    ),
    (
        :id2, 'Corazon Reyes Magno', 'Senior Citizen', 'Magno', 'Corazon', 'Reyes', '1952-08-25',
        '09223317890', '42 Sampaguita St., Pinagbuhatan, Pasig City', '09334456712', 'Roberto Magno',
        'Pinagbuhatan', 'pending', 'Received', 'normal', 'Female', 'Widowed',
        'Mandaluyong City', 'Filipino'
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id1' => $id1, ':id2' => $id2]);
    echo "2 sample Pinagbuhatan records inserted successfully.\n";
    echo "ID 1: $id1\n";
    echo "ID 2: $id2\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
