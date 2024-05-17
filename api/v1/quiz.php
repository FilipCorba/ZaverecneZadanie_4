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
    if ($method === 'GET') {
      handleGetQR($quizHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  case 'question':
    if ($method === 'PUT') {
      handleQuestionChange($dbHandler, $tokenHandler);
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
      case 'POST':
        handleCreateQuiz($quizHandler, $tokenHandler);
        break;
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
  case 'subjects':
    if ($method === 'GET') {
      handleGetListOfSubjects($tokenHandler, $dbHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  case 'start-vote':
    if ($method === 'POST') {
      handleStartVote($dbHandler, $tokenHandler, $quizHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  case 'end-vote':
    if ($method === 'POST') {
      handleEndVote($dbHandler, $tokenHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  case 'vote':
    if ($method === 'POST') {
      handleSendVote($dbHandler, $tokenHandler, $quizHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  case 'survey':
    if ($method === 'GET') {
      handleGetSurvey($quizHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  case 'voting-list':
    if ($method === 'GET') {
      handleGetVotingList($dbHandler, $tokenHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  case 'export':
    if ($method === 'GET') {
      handleExport($dbHandler, $tokenHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  case 'statistics':
    if ($method === 'GET') {
      handleGetVoteStatistics($dbHandler, $tokenHandler);
    } else {
      handleInvalidRequestMethod();
    }
    break;
  default:
    handleInvalidEndpoint();
    break;
}


function handleCreateQuiz($quizHandler, $tokenHandler)
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
  $responseData = $quizHandler->insertQuizData($data);
  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleGetSurvey($quizHandler)
{
  $code = isset($_GET['code']) ? $_GET['code'] : null;
  echo $quizHandler->getSurvey($code);
}
function handleGetQR($quizHandler)
{
  $participationId = isset($_GET['participation-id']) ? $_GET['participation-id'] : null;

  if ($participationId) {
    $qrCode = $quizHandler->generateQrCode($participationId);

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


function handleGetListOfSubjects($tokenHandler, $dbHandler)
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

  echo $dbHandler->getListOfSubjects($userId);
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
  $quiz = $dbHandler->getQuizById($quizId, $userId);

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

function handleQuestionChange($dbHandler, $tokenHandler)
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
  $requestData = json_decode(file_get_contents('php://input'), true);
  if ($dbHandler->changeQuestion($requestData, $userId)) {
    $responseData = [
      'success' => 'Question was successfuly changed',
    ];
  }

  echo json_encode($responseData, JSON_PRETTY_PRINT);
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
    if ($dbHandler->quizExists($quizId, $userId)) {
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
  $success = $dbHandler->deleteQuiz($quizId, $userId);

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

function handleStartVote($dbHandler, $tokenHandler, $quizHandler)
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

  do {
    $randomCode = $quizHandler->generateRandomCode(5);
    $codeExists = $dbHandler->checkIfQuizCodeExists($randomCode);
  } while ($codeExists);


  if ($dbHandler->quizExists($quizId, $userId)) {
    $participationId = $dbHandler->startVote($quizId, $randomCode);
    $responseData = [
      'participation_id' => $participationId
    ];
  } else {
    $responseData = [
      'error' => 'Quiz not found',
    ];
    http_response_code(404);
  }

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleEndVote($dbHandler, $tokenHandler)
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

  $json = file_get_contents('php://input');
  $requestData = json_decode($json, true);

  $note = isset($requestData['note']) ? $requestData['note'] : null;
  $participationId = isset($requestData['participation_id']) ? $requestData['participation_id'] : null;

  $quizParticipation = $dbHandler->doesParticipationExist($participationId);

  if ($quizParticipation) {
    $success = $dbHandler->endVote($note, $participationId);

    if ($success) {
      $responseData = [
        'success' => 'Vote was successfully closed'
      ];
      http_response_code(200);
    } else {
      $responseData = [
        'error' => 'This vote was already closed',
      ];
      http_response_code(400);
    }
  } else {
    $responseData = [
      'error' => 'Voting with given ID does not exist',
    ];
    http_response_code(404);
  }

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}


function handleSendVote($dbHandler, $tokenHandler, $quizHandler)
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

  $json = file_get_contents('php://input');
  $requestData = json_decode($json, true);

  $quizHandler->processVote($requestData, $dbHandler);

  $responseData = [
    'success' => 'Answers were successfully saved.'
  ];

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleGetVotingList($dbHandler, $tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;
  $quizId = isset($_GET['quiz-id']) ? $_GET['quiz-id'] : null;

  $token = $tokenHandler->getTokenFromAuthorizationHeader();
  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  $responseData = $dbHandler->getVoteList($quizId);

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

// TO DO refactor token validation
// this has been tested through browser, not postman
function handleExport($dbHandler, $tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;
  $participationId = isset($_GET['participation-id']) ? $_GET['participation-id'] : null;

  $token = $tokenHandler->getTokenFromAuthorizationHeader();
  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  $participation = $dbHandler->getParticipation($participationId);

  header('Content-Type: application/json');
  // force download
  header('Content-Disposition: attachment; filename="participation.json"');
  echo json_encode($participation);
}

function handleGetVoteStatistics($dbHandler, $tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;
  $participationId = isset($_GET['participation-id']) ? $_GET['participation-id'] : null;

  $token = $tokenHandler->getTokenFromAuthorizationHeader();
  if (!$tokenHandler->isValidToken($token, $userId)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  $responseData = $dbHandler->getVoteStatistics($participationId);

  echo json_encode($responseData, JSON_PRETTY_PRINT);
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
