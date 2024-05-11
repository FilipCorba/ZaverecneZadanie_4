<?php

require_once 'config.php';
require_once 'qr.php';

header('Content-Type: application/json');

$qr = new QR($db);

$method = $_SERVER['REQUEST_METHOD'];
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$lastUri = basename($uri);

// Check if the user making the request is an admin
$isAdmin = isAdmin(); // Implement this function to check if the user is an admin

switch ($lastUri) {
    case 'generate-qr':
        // TO DO remove isAdmin
        handleGenerateQR($method, true, $qr);
        break;
    default:
        $responseData = [
            'error' => 'Invalid endpoint'
        ];
        echo json_encode($responseData);
        break;
}

function handleGenerateQR($method, $isAdmin, $qr) {
    if ($method === 'POST') {
        // Allow only admin users to access the generate-qr endpoint
        if ($isAdmin) {
            $data = json_decode(file_get_contents('php://input'), true);
            $responseData = $qr->generateQrCode($data['data']);
            echo json_encode($responseData, JSON_PRETTY_PRINT);
        } else {
            $responseData = [
                'error' => 'Unauthorized access'
            ];
            echo json_encode($responseData);
        }
    } else {
        $responseData = [
            'error' => 'Invalid request method'
        ];
        echo json_encode($responseData);
    }
}

// Function to check if the user is an admin
function isAdmin() {
    global $db;

    // Assuming you have some way to identify the current user, such as a session variable
    $userId = $_SESSION['user_id']; // Adjust this based on how you store user information

    // Prepare and execute query to fetch the role of the user
    $stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $role = $user['role'];

        // Check if the user's role is admin
        if ($role === 'admin') {
            return true;
        }
    }

    return false;
}