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

$endpointHandlers = [
  'GET' => [
    'generate-qr' => 'handleGetQR',
    'quiz-list' => 'handleGetListOfQuizzes',
    'subjects' => 'handleGetListOfSubjects',
    'survey' => 'handleGetSurvey',
    'voting-list' => 'handleGetVotingList',
    'statistics' => 'handleGetVoteStatistics',
  ],
  'POST' => [
    'start-vote' => 'handleStartVote',
    'end-vote' => 'handleEndVote',
    'vote' => 'handleSendVote',
    'quiz' => 'handleCreateQuiz',
  ],
  'PUT' => [
    'question' => 'handleQuestionChange',
    'quiz' => 'handleQuizTitleChange',
  ],
  'DELETE' => [
    'question' => 'handleQuestionDelete',
    'quiz' => 'handleQuizDelete',
  ],
];

// Handle the request
if (isset($endpointHandlers[$method][$lastUri])) {
  $handlerFunction = $endpointHandlers[$method][$lastUri];
  $handlerFunction($dbHandler, $tokenHandler, $quizHandler);
} else {
  handleInvalidRequestMethod();
}




function handleCreateQuiz($dbHandler, $tokenHandler, $quizHandler)
{
  $requestData = json_decode(file_get_contents('php://input'), true);
  $data = $requestData['data'];
  
  $tokenHandler->validateToken($tokenHandler, $data['user_id']);

  $responseData = $quizHandler->insertQuizData($data);
  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleGetSurvey($quizHandler)
{
  $code = isset($_GET['code']) ? $_GET['code'] : null;
  echo $quizHandler->getSurvey($code);
}

// TO DO - add token validation
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

  $tokenHandler->validateToken($userId);

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


function handleGetListOfSubjects($dbHandler, $tokenHandler, $quizHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;

  $tokenHandler->validateToken($userId);

  echo $dbHandler->getListOfSubjects($userId);
}

function handleGetQuiz($dbHandler, $tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;

  $tokenHandler->validateToken($userId);

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

}

function handleQuestionDelete($dbHandler, $tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;

  $tokenHandler->validateToken($userId);

  // TO DO: do we want to check quiz-id?
  $quizId = isset($_GET['quiz-id']) ? $_GET['quiz-id'] : null;
  if (!$quizId) {
    $responseData = [
      'error' => 'Quiz ID not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

  $questionId = isset($_GET['question-id']) ? $_GET['question-id'] : null;

  if (!$questionId) {
    $responseData = [
      'error' => 'Question id not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

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
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;

  $tokenHandler->validateToken($userId);

  $quizId = isset($_GET['quiz-id']) ? $_GET['quiz-id'] : null;
  if (!$quizId) {
    $responseData = [
      'error' => 'Quiz ID not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

  $requestData = json_decode(file_get_contents('php://input'), true);
  $newTitle = isset($requestData['new-title']) ? $requestData['new-title'] : null;

  if (!$newTitle) {
    $responseData = [
      'error' => 'New title not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

  $success = $dbHandler->updateQuizTitle($quizId, $newTitle);

  if ($success) {
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
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;
  $tokenHandler->validateToken($userId);

  $quizId = isset($_GET['quiz-id']) ? $_GET['quiz-id'] : null;
  if (!$quizId) {
    $responseData = [
      'error' => 'Quiz ID not provided'
    ];
    http_response_code(400);
    echo json_encode($responseData);
    exit;
  }

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

  $tokenHandler->validateToken($userId);

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

  $tokenHandler->validateToken($userId);

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

  $tokenHandler->validateToken($userId);

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

  $tokenHandler->validateToken($userId);

  $responseData = $dbHandler->getVoteList($quizId);

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleGetVoteStatistics($dbHandler, $tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;
  $participationId = isset($_GET['participation-id']) ? $_GET['participation-id'] : null;

  $tokenHandler->validateToken($userId);

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
