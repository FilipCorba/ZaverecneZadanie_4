<?php


global $db;
require_once 'config.php';
require_once 'qr.php';
require_once 'token.php';

header('Content-Type: application/json');

$quizHandler = new QuizHandler($db);
$tokenHandler = new Token();

$method = $_SERVER['REQUEST_METHOD'];
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$lastUri = basename($uri);

switch ($lastUri) {
  case 'generate-qr':
    if ($method === 'POST') {
      handleGenerateQR($quizHandler, $tokenHandler);
    } elseif ($method === 'GET') {
      handleGetQR($quizHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;

  case 'question':
    if ($method === 'PUT') {
      // handleQuestionChange($tokenHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;

  case 'quiz':
    if ($method === 'GET') {
      if (isset($_GET['quizId'])) {
        handleGetQuiz($quizHandler, $tokenHandler);
      } else {
        // handleGetListOfQuizzes();
      }
    } else {
      handleInvalidRequestMethod();
    }
    break;
  default:
    handleInvalidEndpoint();
    break;
}


function handleGenerateQR($quizHandler, $tokenHandler)
{
  $token = $tokenHandler->getTokenFromAuthorizationHeader();

  $requestData = json_decode(file_get_contents('php://input'), true);
  $data = $requestData['data'];
  if (!$tokenHandler->isValidToken($token, $data['user_id'])) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }
  $responseData = $quizHandler->generateQrCodeAndInsertQuizData($data);
  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleGetQR($quizHandler)
{
  $code = isset($_GET['code']) ? $_GET['code'] : null;

  if ($code) {
    $qrCode = $quizHandler->generateQrCode($code);

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

function handleGetListOfQuizzes()
{
}

function handleGetQuiz($quizHandler, $tokenHandler)
{
  $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
  // $user = getUserById($userId);

  $token = $tokenHandler->getTokenFromAuthorizationHeader();
  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  $quizId = isset($_GET['quizId']) ? $_GET['quizId'] : null;
  $quiz = $quizHandler->getQuizById($quizId);

  if ($quiz) {
    $responseData = $quiz;
  } else {
    $responseData = [
      'error' => 'Quiz not found',
    ];
    http_response_code(404);
  }

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleQuestionChange($tokenHandler)
{
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
