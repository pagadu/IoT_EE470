<?php
// ====== UPDATE THESE 3 LINES ======
$DB_HOST = "localhost";
$DB_NAME = "sfdsffs";
$DB_USER = "fsdfsfs";
$DB_PASS = "sdfs";  // <-- type your MySQL password here

function db() {
  global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }
  $conn->set_charset("utf8mb4");
  return $conn;
}
?>
