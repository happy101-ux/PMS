<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit(json_encode(['success' => false, 'message' => 'Invalid input']));

try {
    $section = $input['section'] ?? null;
    $ob_number = $input['ob_number'] ?? null;

    if (!$section || !$ob_number) {
        throw new Exception("Section or OB number missing");
    }

    // Section 1: Report Info
    if ($section == 1) {
        $station = $input['station'] ?? null;
        $date_reported = $input['date_reported'] ?? null;
        $time_reported = $input['time_reported'] ?? null;

        $stmt = $conn->prepare("INSERT INTO complaints (ob_number, station, date_reported, time_reported) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE station=?, date_reported=?, time_reported=?");
        $stmt->bind_param("sssssss", $ob_number, $station, $date_reported, $time_reported, $station, $date_reported, $time_reported);
        $stmt->execute();
        $stmt->close();

    // Section 2: Complainant Info
    } elseif ($section == 2) {
        $stmt = $conn->prepare("UPDATE complaints SET full_name=?, id_number=?, dob=?, gender=?, occupation=?, residential_address=?, postal_address=?, phone=?, next_of_kin=?, next_of_kin_contact=? WHERE ob_number=?");
        $stmt->bind_param("sssssssssss",
            $input['full_name'] ?? '',
            $input['id_number'] ?? '',
            $input['dob'] ?? '',
            $input['gender'] ?? '',
            $input['occupation'] ?? '',
            $input['residential_address'] ?? '',
            $input['postal_address'] ?? '',
            $input['phone'] ?? '',
            $input['next_of_kin'] ?? '',
            $input['next_of_kin_contact'] ?? '',
            $ob_number
        );
        $stmt->execute();
        $stmt->close();

    // Section 3: Offence Info
    } elseif ($section == 3) {
        $stmt = $conn->prepare("UPDATE complaints SET offence_type=?, date_occurrence=?, time_occurrence=?, place_occurrence=? WHERE ob_number=?");
        $stmt->bind_param("sssss",
            $input['offence_type'] ?? '',
            $input['date_occurrence'] ?? '',
            $input['time_occurrence'] ?? '',
            $input['place_occurrence'] ?? '',
            $ob_number
        );
        $stmt->execute();
        $stmt->close();

    // Section 4: Suspect Info
    } elseif ($section == 4) {
        $stmt = $conn->prepare("UPDATE complaints SET suspect_name=?, suspect_address=? WHERE ob_number=?");
        $stmt->bind_param("sss",
            $input['suspect_name'] ?? '',
            $input['suspect_address'] ?? '',
            $ob_number
        );
        $stmt->execute();
        $stmt->close();

    // Section 5: Witnesses
    } elseif ($section == 5) {
        $witnesses = json_encode($input['witnesses'] ?? []);
        $stmt = $conn->prepare("UPDATE complaints SET witnesses=? WHERE ob_number=?");
        $stmt->bind_param("ss", $witnesses, $ob_number);
        $stmt->execute();
        $stmt->close();

    // Section 6: Statement
    } elseif ($section == 6) {
        $statement = $input['statement'] ?? '';
        $stmt = $conn->prepare("UPDATE complaints SET statement=? WHERE ob_number=?");
        $stmt->bind_param("ss", $statement, $ob_number);
        $stmt->execute();
        $stmt->close();

    // Section 7: Declaration & Remarks
    } elseif ($section == 7) {
        $stmt = $conn->prepare("UPDATE complaints SET declaration_name=?, signature=?, declaration_date=?, received_by=?, rank=?, force_number=?, remarks=? WHERE ob_number=?");
        $stmt->bind_param("ssssssss",
            $input['declaration_name'] ?? '',
            $input['signature'] ?? '',
            $input['declaration_date'] ?? '',
            $input['received_by'] ?? '',
            $input['rank'] ?? '',
            $input['force_number'] ?? '',
            $input['remarks'] ?? '',
            $ob_number
        );
        $stmt->execute();
        $stmt->close();
    } else {
        throw new Exception("Invalid section");
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
