<?php


global $db;
require_once 'config.php';
require_once 'quizService.php';
require_once 'token.php';

header('Content-Type: application/json');

$quizHandler = new QuizHandler($db);
$tokenHandler = new Token();
$dbHandler = new dbHandler($db); // TO DO rename to DBHandler

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
    } elseif ($method === 'DELETE') {
      if (isset($_GET['user-id']) && isset($_GET['quiz-id']) && isset($_GET['question-id'])) {
        handleQuestionDelete($dbHandler, $tokenHandler);
      } else {
        handleInvalidRequestMethod();
      }
    } else {
      handleInvalidRequestMethod();
    }
    break;

  case 'quiz':
    switch ($method) {
      case 'GET':
        handleGetQuiz($dbHandler, $tokenHandler);
        break;
      case 'PUT':
        if (isset($_GET['quiz-id']) && isset($_GET['user-id'])) {
          handleQuizTitleChange($dbHandler, $tokenHandler);
        } else {
          handleInvalidRequestMethod();
        }
        break;
      case 'DELETE':
        if (isset($_GET['quiz-id']) && isset($_GET['user-id'])) {
          handleQuizDelete($dbHandler, $tokenHandler);
        } else {
          handleInvalidRequestMethod();
        }
        break;
      default:
        handleInvalidRequestMethod();
        break;
    }
    break;
  case 'quiz-list':
    if ($method === 'GET') {
      handleGetListOfQuizzes($dbHandler, $tokenHandler);
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

function handleGetListOfQuizzes($dbHandler, $tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;

  $token = $tokenHandler->getTokenFromAuthorizationHeader();
  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  $quizList = $dbHandler->getListOfQuizzes($userId);

  if ($quizList) {
    $responseData = $quizList;
  } else {
    $responseData = [
      'error' => 'Quizes not found',
    ];
    http_response_code(404);
  }

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleGetQuiz($dbHandler, $tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;

  $token = $tokenHandler->getTokenFromAuthorizationHeader();
  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  $quizId = isset($_GET['quiz-id']) ? $_GET['quiz-id'] : null;
  $quiz = $dbHandler->getQuizById($quizId);

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
function handleQuestionChange($dbHandler)
{

}
function handleQuestionDelete($dbHandler, $tokenHandler)
{
  // Get user ID from URL
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;
  // Get token from authorization header
  $token = $tokenHandler->getTokenFromAuthorizationHeader();

  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  // Get quiz ID from URL
  $quizId = isset($_GET['quiz-id']) ? $_GET['quiz-id'] : null;
  // Check if quiz ID is provided
  if (!$quizId) {
    $responseData = [
      'error' => 'Quiz ID not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

  $questionId = isset($_GET['question-id']) ? $_GET['question-id'] : null;

  // Check if new title is provided
  if (!$questionId) {
    $responseData = [
      'error' => 'Question id not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

  // Delete the question
  $success = $dbHandler->deleteQuestion($quizId, $questionId);

  if ($success) {
    $responseData = [
      'success' => 'Question deleted successfully'
    ];
    http_response_code(200);
  } else {
    $responseData = [
      'error' => 'Failed to delete question'
    ];
    http_response_code(500);
  }
  echo json_encode($responseData);
}

function handleQuizTitleChange($dbHandler, $tokenHandler)
{
  // Get user ID from URL
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;
  // Get token from authorization header
  $token = $tokenHandler->getTokenFromAuthorizationHeader();

  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  // Get quiz ID from URL
  $quizId = isset($_GET['quiz-id']) ? $_GET['quiz-id'] : null;
  // Check if quiz ID is provided
  if (!$quizId) {
    $responseData = [
      'error' => 'Quiz ID not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

  // Get new title from request body
  $requestData = json_decode(file_get_contents('php://input'), true);
  $newTitle = isset($requestData['new-title']) ? $requestData['new-title'] : null;

  // Check if new title is provided
  if (!$newTitle) {
    $responseData = [
      'error' => 'New title not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

  // Update quiz title
  $success = $dbHandler->updateQuizTitle($quizId, $newTitle);

  if ($success) {
    // Get quiz by ID
    $quiz = $dbHandler->getQuizById($quizId);

    if ($quiz) {
      $responseData = [
        'success' => 'Quiz title update successful',
      ];
      http_response_code(200);
    } else {
      $responseData = [
        'error' => 'Quiz not found',
      ];
      http_response_code(404);
    }
  } else {
    $responseData = [
      'error' => 'Failed to update quiz title'
    ];
    http_response_code(500);
  }

  echo json_encode($responseData);
}
function handleQuizDelete($dbHandler, $tokenHandler)
{
  // Get user ID from URL
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;
  // Get token from authorization header
  $token = $tokenHandler->getTokenFromAuthorizationHeader();

  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  // Get quiz ID from URL
  $quizId = isset($_GET['quiz-id']) ? $_GET['quiz-id'] : null;
  // Check if quiz ID is provided
  if (!$quizId) {
    $responseData = [
      'error' => 'Quiz ID not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

  // Delete the quiz
  $success = $dbHandler->deleteQuiz($quizId);

  if ($success) {
    $responseData = [
      'success' => 'Quiz deleted successfully'
    ];
    http_response_code(200);
  } else {
    $responseData = [
      'error' => 'Failed to delete quiz'
    ];
    http_response_code(500);
  }

  echo json_encode($responseData);
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
