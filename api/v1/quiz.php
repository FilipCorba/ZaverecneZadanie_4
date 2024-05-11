<?php

require_once 'config.php';
require_once 'qr.php';

header('Content-Type: application/json');

$qr = new QR($db);

$method = $_SERVER['REQUEST_METHOD'];

$uri = strtok($_SERVER['REQUEST_URI'], '?');
$lastUri = basename($uri);

switch ($method) {
  case 'POST':
    if ($lastUri == 'generate-qr') {
      $data = json_decode(file_get_contents('php://input'), true);
      $responseData = $qr->generateQrCode($data['data']);
      echo json_encode($responseData, JSON_PRETTY_PRINT);
    } else {
      $responseData = [
        'error' => 'Invalid endpoint'
      ];
      echo json_encode($responseData);
    }
    break;
  default:
    $responseData = [
      'error' => 'Invalid request method'
    ];
    echo json_encode($responseData);
    break;
}

