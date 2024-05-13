<?php

require_once 'config.php';
require_once 'token.php';

$tokenHandler = new Token();

$method = $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json');
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$lastUri = basename($uri);

switch ($method) {
  case 'POST':
    switch ($lastUri) {

      case 'login':
        handleLogin($tokenHandler);
        break;

      case 'register':
        handleRegistration($tokenHandler);
        break;

      case 'password-change':
        handlePasswordChange();
        break;

      default:
        handleInvalidEndpoint();
        break;
    }
    break;

  case 'PUT':
    switch ($lastUri) {
      case 'role':
        handleRoleChange($tokenHandler);
        break;
     
      default:
        handleInvalidEndpoint();
        break;
    }
    break;
  
  case 'GET':
    switch ($lastUri) {
      case 'user':
        if (isset($_GET['user-id'])) {
          handleGetUser($tokenHandler);
        } else {
          // handleGetListOfQuizzes();
        }
        break;
      case 'users':
        handleGetUsers($tokenHandler);
        break;
      default:
        handleInvalidEndpoint();
        break;
    }
    break;
  default:
    handleInvalidRequestMethod();
    break;
}

function handleGetUser($tokenHandler)
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

  if ($userId != null) {
    $userInfo = getUserById($userId);
    if ($userInfo) {
      $jsonResponse = json_encode([
          'username' => $userInfo['name'],
          'email' => $userInfo['mail'],
          'role' => $userInfo['role']
      ]);
      echo $jsonResponse;
    } else {
      echo json_encode(['error' => 'Id is null']);
    }
  }
  else{
    echo json_encode(['error' => 'User not found']);
  }
}

function handleGetUsers($tokenHandler)
{
  $token = $tokenHandler->getTokenFromAuthorizationHeader();
  if (!$tokenHandler->isAdminToken($token)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }
  $usersInfo = getUsers();
  $users = [];
  while ($row = $usersInfo->fetch_assoc()) {
      $user = [
          'user_id' => $row['user_id'],
          'name' => $row['name'],
          'mail' => $row['mail'],
          'role' => $row['role']
      ];
      $users[] = $user;
  }
  $jsonResponse = json_encode($users);
  echo $jsonResponse;
}

function handlePasswordChange()
{
  $data = json_decode(file_get_contents('php://input'), true);
  //timestamp-prvykrat overenie hesla,tak zacne odpocet a ak do 5 min nepride nove heslo,tak sa akcia nekona...404 zmeskane
  $userId = $data['user_id'];
  $password = $data['password'];
  $newPassword = $data['new_password'];

  $user = getUserById($userId);
  if ($user) {
    if (password_verify($password, $user['password'])) {
      // Password verification successful, update the password
      $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      updateUserPassword($userId, $hashedNewPassword);

      $responseData = [
        'success' => 'Password changed successfully',
        'user' => [
          'id' => $user['user_id'],
          'username' => $user['name'],
          'email' => $user['mail'],
        ],
      ];
    } else {
      // Invalid current password
      $responseData = [
        'error' => 'Invalid current password',
      ];
      http_response_code(400);
    }
  } else {
    // User not found
    $responseData = [
      'error' => 'User not found',
    ];
    http_response_code(404);
  }

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function getUserById($userId)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc();
  
}

function getUsers()
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users");
  $stmt->execute();
  return $stmt->get_result();
}

function isPasswordChangeRequestValid($timestamp)
{
  $allowedTimeframe = 5 * 60; // 5 minutes in seconds
  $currentTime = time();
  $timeDifference = $currentTime - strtotime($timestamp);

  // Check if the time difference is within the allowed timeframe
  return $timeDifference <= $allowedTimeframe;
}

function updateUserPassword($userId, $newPassword)
{
  global $db;
  $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
  $stmt->bind_param("si", $newPassword, $userId);
  $stmt->execute();
}

function handleRoleChange($tokenHandler)
{
  $token = $tokenHandler->getTokenFromAuthorizationHeader();
  if (!$tokenHandler->isAdminToken($token)) {
    $responseData = [
      'error' => 'Unauthorized token'
    ];
    http_response_code(403);
    echo json_encode($responseData);
    exit;
  }

  $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
  $user = getUserById($userId);

  if ($user) {
    changeUserRoleToAdmin($userId);
    $responseData = [
      'success' => 'User was succesfully changed to admin',
    ];
  } else {
    $responseData = [
      'error' => 'User not found',
    ];
    http_response_code(404);
  }

  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleLogin($tokenHandler)
{
  $data = json_decode(file_get_contents('php://input'), true);
  $username = $data['username'];
  $password = $data['password'];

  $user = getUserByName($username);

  if ($user) {
    if (password_verify($password, $user['password'])) {
      // Generate token for the logged in user and save it into DB
      $token = $tokenHandler->generateToken();
      $tokenHandler->saveToken($token, $user['user_id']);

      $responseData = [
        'success' => 'Login successful',
        'user' => [
          'id' => $user['user_id'],
          'username' => $user['name'],
          'email' => $user['mail'],
        ],
        'token' => $token
      ];
    } else {
      $responseData = [
        'error' => 'Invalid password',
      ];
      http_response_code(400);
    }
  } else {
    $responseData = [
      'error' => 'User not found',
    ];
    http_response_code(404);
  }
  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function handleRegistration($tokenHandler)
{
  $data = json_decode(file_get_contents('php://input'), true);
  $username = $data['username'];
  $password = $data['password'];
  $email = $data['email'];

  $user = getUserByName($username);

  $userWithEmail = getUserByEmail($email);

  if ($user) {
    $responseData = [
      'error' => 'Username already exists',
    ];
    http_response_code(400);
  } else if ($userWithEmail) {
    $responseData = [
      'error' => 'Email already exists',
    ];
    http_response_code(400);
  } else {
    $userId = insertUser($username, $password, $email);

    // Generate token for the registered user and save it into DB
    $token = $tokenHandler->generateToken();
    $tokenHandler->saveToken($token, $userId);

    $responseData = [
      'success' => 'User ' . $username . ' registered successfully',
      'user_id' => $userId, // Include the user ID in the response
      'token' => $token
    ];
  }
  echo json_encode($responseData, JSON_PRETTY_PRINT);
}

function getUserByName($username)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users WHERE name = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc();
}

function getUserByEmail($email)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users WHERE mail = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc();
}

function insertUser($username, $password, $email)
{
  global $db;
  $stmt = $db->prepare("INSERT INTO users (name, password, mail) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $username, password_hash($password, PASSWORD_DEFAULT), $email);
  $stmt->execute();
  return $stmt->insert_id;
}

function changeUserRoleToAdmin($userId)
{
  global $db;
  $stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE user_id = ?;");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
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
