<?php

require_once 'config.php';
require_once 'qr.php';

header('Content-Type: application/json');

$qr = new QR($db);

$method = $_SERVER['REQUEST_METHOD'];
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$lastUri = basename($uri);

switch ($lastUri) {
  case 'generate-qr':
    handleGenerateQR($method, $qr);
    break;
  default:
    $responseData = [
      'error' => 'Invalid endpoint'
    ];
    echo json_encode($responseData);
    break;
}

function handleGenerateQR($method, $qr)
{
  // Check if the request contains a valid token in the Authorization header
  $token = getTokenFromAuthorizationHeader();
  if (!isValidToken($token)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    echo json_encode($responseData);
    exit;
  }

  if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $responseData = $qr->generateQrCode($data['data']);
    echo json_encode($responseData, JSON_PRETTY_PRINT);
  } else {
    $responseData = [
      'error' => 'Invalid request method'
    ];
    echo json_encode($responseData);
  }
}

function isValidToken($token)
{
  global $db;

  // Prepare and execute query to check if the token is valid
  $stmt = $db->prepare(
    "SELECT COUNT(*) as count 
    FROM tokens 
      WHERE token = ? 
      -- AND user_id = ? 
      AND expiration_timestamp > NOW()");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();

  // If count > 0, token is valid
  return $result['count'] > 0;
}

function getTokenFromAuthorizationHeader()
{
  $headers = apache_request_headers();

  if (isset($headers['Authorization'])) {
    $authorizationHeader = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $authorizationHeader);
    return $token;
  }

  return '';
}
