<?php

require_once 'config.php';
require_once 'token.php';

$tokenHandler = new Token();

$method = $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json');
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$lastUri = basename($uri);

// Define endpoint handlers
$endpointHandlers = [
  'POST' => [
    'login' => 'handleLogin',
    'register' => 'handleRegistration',
    'password-change' => 'handlePasswordChange',
  ],
  'PUT' => [
    'role' => 'handleRoleChange',
  ],
  'GET' => [
    'user' => 'handleGetUser',
    'users' => 'handleGetUsers',
    'admins' => 'handleGetAdmins',
  ],
];

// Handle the request
if (isset($endpointHandlers[$method])) {
  $methodHandlers = $endpointHandlers[$method];
  if (isset($methodHandlers[$lastUri])) {
    $handlerFunction = $methodHandlers[$lastUri];
    if (function_exists($handlerFunction)) {
      // Call the appropriate handler function
      $handlerFunction($tokenHandler);
    } else {
      handleInvalidEndpoint();
    }
  } else {
    handleInvalidEndpoint();
  }
} else {
  handleInvalidRequestMethod();
}


function handleGetUser($tokenHandler)
{
  $userId = isset($_GET['user-id']) ? $_GET['user-id'] : null;

  $tokenHandler->validateToken($userId);

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
  } else {
    echo json_encode(['error' => 'User not found']);
  }
}

function handleGetUsers($tokenHandler)
{
  $tokenHandler->validateAdminToken();

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

function handleGetAdmins($tokenHandler)
{
  $tokenHandler->validateAdminToken();

  $adminsInfo = getAdmins();
  $admins = [];
  while ($row = $adminsInfo->fetch_assoc()) {
    $admin = [
      'user_id' => $row['user_id'],
      'name' => $row['name'],
      'mail' => $row['mail'],
      'role' => $row['role']
    ];
    $admins[] = $admin;
  }
  $jsonResponse = json_encode($admins);
  echo $jsonResponse;
}

function handlePasswordChange($tokenHandler)
{
  $data = json_decode(file_get_contents('php://input'), true);
  //timestamp-prvykrat overenie hesla,tak zacne odpocet a ak do 5 min nepride nove heslo,tak sa akcia nekona...404 zmeskane
  $userId = $data['user_id'];
  $password = $data['password'];
  $newPassword = $data['new_password'];
  $tokenHandler->validateToken($userId);

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

function isPasswordChangeRequestValid($timestamp)
{
  $allowedTimeframe = 5 * 60; // 5 minutes in seconds
  $currentTime = time();
  $timeDifference = $currentTime - strtotime($timestamp);

  // Check if the time difference is within the allowed timeframe
  return $timeDifference <= $allowedTimeframe;
}

function handleRoleChange($tokenHandler)
{
  $tokenHandler->validateAdminToken();

  $userId = isset($_GET['userId']) ? $_GET['userId'] : null;

  $role = getRole($userId);

  $numberOfAdmins = getAdmins()->num_rows;

  if ($role == 'admin' && $numberOfAdmins === 1) {
    $responseData = [
      'error' => 'Change of role was not successful, there is only one admin left.',
    ];
    http_response_code(403);
  } else {
    changeUserRole($userId);

    $responseData = [
      'success' => 'Role was successfully changed',
    ];
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
          'role' => $user['role'],
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




// DB calls

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

function changeUserRole($userId)
{
  global $db;
  $stmt = $db->prepare("UPDATE users 
                        SET role = (CASE 
                          WHEN role = 'user' 
                          THEN 'admin' 
                          ELSE 'user' 
                        END) 
                          WHERE user_id = ?;");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
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

function getAdmins()
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users where role = 'admin'");
  $stmt->execute();
  return $stmt->get_result();
}

function getUsers()
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM users");
  $stmt->execute();
  return $stmt->get_result();
}

function updateUserPassword($userId, $newPassword)
{
  global $db;
  $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
  $stmt->bind_param("si", $newPassword, $userId);
  $stmt->execute();
}

function getRole($userId)
{
  global $db;
  $stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $roleData = $result->fetch_assoc();
  return $roleData['role'];
}
