<?php
// config.php — single source of truth

// --- EDIT THESE 4 VALUES ---
$DB_HOST = "localhost";
$DB_NAME = "alexpag";
$DB_USER = "dbuser"; // keep as-is if that's the real user
$DB_PASS = "mypass";                 // your real password

// Debug while fixing; set to false when stable
define('APP_DEBUG', true);
if (APP_DEBUG) {
  ini_set('display_errors','1');
  ini_set('display_startup_errors','1');
  error_reporting(E_ALL);
}

/* ---------- REQUIRED: db() ---------- */
function db() {
  global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS;
  $conn = @new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
  if ($conn->connect_errno) {
    throw new Exception("DB connect failed ({$conn->connect_errno}): ".$conn->connect_error);
  }
  $conn->set_charset('utf8mb4');
  return $conn;
}

/* ---------- Helpers (guarded) ---------- */
if (!function_exists('fail')) {
  function fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["ok"=>false,"message"=>$msg], JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('ok')) {
  function ok($data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["ok"=>true] + $data, JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('clean_str')) {
  function clean_str($s){ return trim((string)$s); }
}
