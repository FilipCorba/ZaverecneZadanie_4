<?php

// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'xuhrinovat');
define('DB_PASSWORD', 'MojaDatabaza.2002');
define('DB_NAME', 'survey');
define('PERSONAL_CODE', 117);

$db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

?>