<?php
require_once __DIR__ . "/config.php";
$conn = db();
header('Content-Type: application/json; charset=utf-8');

function fail($m){ http_response_code(400); echo json_encode(["ok"=>false,"message"=>$m]); exit; }
function ok($data){ echo json_encode(["ok"=>true,"message"=>"Row inserted successfully"] + $data); exit; }

if (!isset($_GET['bm'])) fail("Missing bm parameter");

$bm = trim($_GET['bm']);
$bm = strtr($bm, '-_', '+/');                      // URL-safe -> standard
$pad = strlen($bm) % 4; if ($pad) $bm .= str_repeat('=', 4 - $pad);

$decoded = base64_decode($bm, true);
if ($decoded === false) fail("Invalid base64 payload");

// Expect: nodeId=...&nodeTemp=...&timeReceived=... (&humidity=... optional)
parse_str($decoded, $q);

$node = $q['nodeId'] ?? null;
$temp = isset($q['nodeTemp']) ? floatval($q['nodeTemp']) : null;
$hum  = isset($q['humidity']) ? floatval($q['humidity']) : null; // optional
$time = $q['timeReceived'] ?? null;

if (!$node || $time===null || $temp===null) fail("Missing required fields");
if (!is_numeric($temp)) fail("nodeTemp must be numeric");
if ($temp < -10 || $temp > 100) fail("Temperature out of range (-10..100)");
if ($hum !== null && ($hum < 0 || $hum > 100)) fail("Humidity out of range (0..100)");

// Optional but nice: unique(node_name,time_received)
@$conn->query("ALTER TABLE sensor_data ADD UNIQUE KEY uniq_node_time (node_name, time_received)");

if ($hum === null) {
  $stmt = $conn->prepare("
    INSERT INTO sensor_data (node_name, time_received, temperature)
    VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE temperature=VALUES(temperature)
  ");
  $stmt->bind_param("ssd", $node, $time, $temp);
} else {
  $stmt = $conn->prepare("
    INSERT INTO sensor_data (node_name, time_received, temperature, humidity)
    VALUES (?,?,?,?)
    ON DUPLICATE KEY UPDATE temperature=VALUES(temperature), humidity=VALUES(humidity)
  ");
  $stmt->bind_param("ssdd", $node, $time, $temp, $hum);
}

if ($stmt && $stmt->execute()) {
  ok([
    "decoded"=>[
      "nodeId"=>$node,
      "nodeTemp"=>$temp,
      "humidity"=>$hum,
      "timeReceived"=>$time
    ],
    "insert_id"=>$stmt->insert_id ?? null
  ]);
} else {
  fail("DB error: ".($stmt ? $stmt->error : $conn->error));
}
