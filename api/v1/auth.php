<?php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json');
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$lastUri = basename($uri);

switch ($method) {
  case 'POST':
    switch ($lastUri) {
      case 'login':
        handleLogin();
        break;
      case 'register':
        handleRegistration();
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


function handleLogin()
{
  global $db;
  $data = json_decode(file_get_contents('php://input'), true);
  $username = $data['username'];
  $password = $data['password'];
  $stmt = $db->prepare("SELECT * FROM users WHERE name = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  if ($user) {
    if (password_verify($password, $user['password'])) {
      // Generate token for the logged in user and save it into DB
      $token = generateToken();
      date_default_timezone_set('Europe/Bratislava');
      $expiration = date('Y-m-d H:i:s', strtotime('+1 day')); // Token expiration in 1 day
      $stmt = $db->prepare("INSERT INTO tokens (token, user_id, expiration_timestamp) VALUES (?, ?, ?)");
      $stmt->bind_param("sis", $token, $user['user_id'], $expiration);

      $stmt->execute();
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
    }
  } else {
    $responseData = [
      'error' => 'User not found',
    ];
  }
  echo json_encode($responseData, JSON_PRETTY_PRINT);
}


function handleRegistration()
{
  global $db;
  $data = json_decode(file_get_contents('php://input'), true);
  $username = $data['username'];
  $password = $data['password'];
  $email = $data['email'];

  $stmt = $db->prepare("SELECT * FROM users WHERE name = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  $stmt = $db->prepare("SELECT * FROM users WHERE mail = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();
  $userWithEmail = $result->fetch_assoc();

  if ($user) {
    $responseData = [
      'error' => 'Username already exists',
    ];
  } else if ($userWithEmail) {
    $responseData = [
      'error' => 'Email already exists',
    ];
  } else {
    $stmt = $db->prepare("INSERT INTO users (name, password, mail) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, password_hash($password, PASSWORD_DEFAULT), $email);
    $stmt->execute();
    $userId = $stmt->insert_id; // Get the ID of the newly inserted user

    // Generate token for the registered user and save it into DB
    $token = generateToken();
    $expiration = date('Y-m-d H:i:s', strtotime('+1 day')); // Token expiration in 1 day

    $stmt = $db->prepare("INSERT INTO tokens (token, user_id, expiration_timestamp) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $token, $userId, $expiration);

    $stmt->execute();

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
  echo json_encode($responseData);
}


function handleInvalidRequestMethod()
{
  $responseData = [
    'error' => 'Invalid request method'
  ];
  echo json_encode($responseData);
}


function generateToken()
{
  return bin2hex(random_bytes(32)); // Generate a random token
}
