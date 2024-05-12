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
    if ($method === 'POST') {
      handleGenerateQR($qr);
    } elseif ($method === 'GET') {
      handleGetQR($qr);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  default:
    handleInvalidEndpoint();
    break;
}


function handleGenerateQR($qr)
{
  $token = getTokenFromAuthorizationHeader();

  $requestData = json_decode(file_get_contents('php://input'), true);
  $data = $requestData['data'];
  if (!isValidToken($token, $data['user'])) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }
  $responseData = $qr->generateQrCodeAndInsertQuizData($data);
  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleGetQR($qr)
{
  $code = isset($_GET['code']) ? $_GET['code'] : null;

  if ($code) {
    $qrCode = $qr->generateQrCode($code);

    if ($qrCode) {
      echo json_encode($qrCode, JSON_PRETTY_PRINT);
    } else {
      $responseData = [
        'error' => 'QR code not found'
      ];
      http_response_code(404);
      echo json_encode($responseData);
    }
  } else {
    $responseData = [
      'error' => 'Missing code parameter'
    ];
    http_response_code(400);
    echo json_encode($responseData);
  }
}

function handleInvalidEndpoint()
{
  $responseData = [
    'error' => 'Invalid endpoint'
  ];
  http_response_code(404);
  echo json_encode($responseData);
}

function handleInvalidRequestMethod()
{
  $responseData = [
    'error' => 'Invalid request method'
  ];
  http_response_code(400);
  echo json_encode($responseData);
}

function isValidToken($token, $userId)
{
  global $db;

  // Prepare and execute query to check if the token is valid
  $stmt = $db->prepare(
    "SELECT COUNT(*) as count 
    FROM tokens t 
    JOIN users u on u.user_id = t.user_id 
      WHERE t.token = ? 
      AND (t.user_id = ? OR u.role = 'admin') 
      AND t.expiration_timestamp > NOW()"
  );
  $stmt->bind_param("si", $token, $userId);
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
