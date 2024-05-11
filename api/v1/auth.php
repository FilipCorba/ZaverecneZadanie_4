<?php


require_once 'config.php';


$method = $_SERVER['REQUEST_METHOD'];

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  echo 'Preflight request';
  header('Access-Control-Allow-Origin: http://localhost:5173');
  header('Access-Control-Allow-Methods: GET, POST');
  header('Access-Control-Allow-Headers: Content-Type');
  exit;
}

$uri = strtok($_SERVER['REQUEST_URI'], '?');

$lastUri = basename($uri);

switch ($method) {
  case 'POST':
    if ($lastUri == 'login') {
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
          $responseData = [
            'success' => 'Login successful',
            'user' => [
              'id' => $user['user_id'],
              'username' => $user['name'],
              'email' => $user['mail'],
            ],
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
    } else if ($lastUri == 'register') {
      $data = json_decode(file_get_contents('php://input'), true);
      $username = $data['username'];
      $password = $data['password'];
      $email = $data['email'];

      $stmt = $db->prepare("SELECT * FROM users WHERE name = ?");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();
      $user = $result->fetch_assoc();

      if ($user) {
        $responseData = [
          'error' => 'Username already exists',
        ];
      } else {
        $stmt = $db->prepare("INSERT INTO users (name, password, mail) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, password_hash($password, PASSWORD_DEFAULT), $email);
        $stmt->execute();

        $responseData = [
          'success' => 'User registered successfully',
        ];
      }

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
