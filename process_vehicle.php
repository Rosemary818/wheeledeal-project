<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update SQL to match exactly with the parameters we're binding
        $vehicle_sql = "INSERT INTO tbl_vehicles (
            seller_id, brand, model, year, price, 
            vehicle_type, fuel_type, transmission, 
            mileage, kilometer, color, registration_type, 
            number_of_owners, guarantee, Address, description,
            front_suspension, rear_suspension, front_brake_type,
            rear_brake_type, minimum_turning_radius, wheels,
            spare_wheel, front_tyres, rear_tyres, status
        ) VALUES (
            ?, ?, ?, ?, ?,                   /* 5 parameters */
            ?, ?, ?,                         /* 3 parameters */
            ?, ?, ?, ?,                      /* 4 parameters */
            ?, ?, ?, ?,                      /* 4 parameters */
            ?, ?, ?,                         /* 3 parameters */
            ?, ?, ?,                         /* 3 parameters */
            ?, ?, ?, 'Active'                /* 3 parameters */
        )";                                  /* Total: 24 parameters */
        
        $vehicle_stmt = $conn->prepare($vehicle_sql);
        
        if ($vehicle_stmt === false) {
            throw new Exception("Error preparing vehicle statement: " . $conn->error);
        }
        
        // Set default values for any potentially missing POST data
        $fuel_type = ($_POST['vehicle_type'] === 'EV') ? 'Electric' : 'Petrol/Diesel';
        
        // Create variables for all parameters to ensure they exist
        $transmission = isset($_POST['transmission']) ? $_POST['transmission'] : '';
        $mileage = isset($_POST['mileage']) ? $_POST['mileage'] : '';
        $kilometer = isset($_POST['kilometer']) ? $_POST['kilometer'] : '';
        $color = isset($_POST['color']) ? $_POST['color'] : '';
        $registration_type = isset($_POST['registration_type']) ? $_POST['registration_type'] : '';
        $number_of_owners = isset($_POST['number_of_owners']) ? $_POST['number_of_owners'] : '';
        $guarantee = isset($_POST['guarantee']) ? $_POST['guarantee'] : '';
        $address = isset($_POST['address']) ? $_POST['address'] : '';
        $description = isset($_POST['description']) ? $_POST['description'] : '';

        $vehicle_stmt->bind_param(
            "sssisssssssssssssssssssss",     // 24 type definitions
            $seller_id,                      // 1
            $_POST['brand'],                 // 2
            $_POST['model'],                 // 3
            $_POST['year'],                  // 4
            $_POST['price'],                 // 5
            $_POST['vehicle_type'],          // 6
            $fuel_type,                      // 7
            $transmission,                   // 8
            $mileage,                        // 9
            $kilometer,                      // 10
            $color,                          // 11
            $registration_type,              // 12
            $number_of_owners,               // 13
            $guarantee,                      // 14
            $address,                        // 15
            $description,                    // 16
            $_POST['front_suspension'],      // 17
            $_POST['rear_suspension'],       // 18
            $_POST['front_brake_type'],      // 19
            $_POST['rear_brake_type'],       // 20
            $_POST['minimum_turning_radius'],// 21
            $_POST['wheels'],                // 22
            $_POST['spare_wheel'],           // 23
            $_POST['front_tyres'],           // 24
            $_POST['rear_tyres']             // 25
        );
        
        $vehicle_stmt->execute();
        $vehicle_id = $conn->insert_id;
        
        // Insert into tbl_ev
        $ev_sql = "INSERT INTO tbl_ev (
            vehicle_id, range_km, battery_capacity,
            charging_time_ac, charging_time_dc,
            electric_motor, max_power, max_torque
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $ev_stmt = $conn->prepare($ev_sql);
        
        if ($ev_stmt === false) {
            throw new Exception("Error preparing EV statement: " . $conn->error);
        }
        
        $ev_stmt->bind_param(
            "isssssss",
            $vehicle_id,
            $_POST['range_km'],
            $_POST['battery_capacity'],
            $_POST['charging_time_ac'],
            $_POST['charging_time_dc'],
            $_POST['electric_motor'],
            $_POST['max_power'],
            $_POST['max_torque']
        );
        
        $ev_stmt->execute();
        
        // Handle photo uploads
        $photo_types = [
            'exterior_photos' => 'exterior',
            'interior_photos' => 'interior',
            'features_photos' => 'features',
            'imperfections_photos' => 'imperfections',
            'highlights_photos' => 'highlights',
            'tyres_photos' => 'tyres'
        ];
        
        foreach ($photo_types as $input_name => $photo_type) {
            if (isset($_FILES[$input_name]) && !empty($_FILES[$input_name]['name'][0])) {
                $uploadDir = 'uploads/vehicle_photos/' . $photo_type . '/';
                
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $files = $_FILES[$input_name];
                $countfiles = count($files['name']);
                
                for($i = 0; $i < $countfiles; $i++) {
                    if (!empty($files['name'][$i])) {
                        $filename = $files['name'][$i];
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        $new_filename = uniqid() . '.' . $ext;
                        $target_file = $uploadDir . $new_filename;
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                            $photo_sql = "INSERT INTO tbl_photos (
                                vehicle_id, photo_file_name, 
                                photo_file_path, category
                            ) VALUES (?, ?, ?, ?)";
                            
                            $photo_stmt = $conn->prepare($photo_sql);
                            
                            if ($photo_stmt === false) {
                                throw new Exception("Error preparing photo statement: " . $conn->error);
                            }
                            
                            $photo_stmt->bind_param(
                                "isss",
                                $vehicle_id,
                                $new_filename,
                                $target_file,
                                $photo_type
                            );
                            $photo_stmt->execute();
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Vehicle listed successfully!";
        header('Location: seller_dashboard.php');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: list_ev.php');
        exit();
    }
}
?>