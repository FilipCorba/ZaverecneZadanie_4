<?php

// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'xcorba');
define('DB_PASSWORD', 'R_nls22PQ_tt_g');
define('DB_NAME', 'survey');

$db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

?>