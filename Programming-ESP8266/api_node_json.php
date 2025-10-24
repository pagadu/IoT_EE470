<?php
// api_node_json.php — robust; supports temperature, humidity, light; matches sensor_activity schema
require_once __DIR__ . "/config.php";
header('Content-Type: application/json; charset=utf-8');

try {
  $conn = db();

  // inputs
  $node   = $_GET['node_name'] ?? null;
  $last   = isset($_GET['last']) ? (int)$_GET['last'] : 50;
  $last   = max(5, min($last, 500));
  $metric = $_GET['metric'] ?? null;

  // discover nodes (prefer register, then data)
  $nodes = [];
  if ($r = $conn->query("SELECT node_name FROM sensor_register ORDER BY node_name ASC")) {
    while ($row = $r->fetch_assoc()) $nodes[] = $row['node_name'];
  }
  if (!$nodes) {
    if ($r = $conn->query("SELECT DISTINCT node_name FROM sensor_data ORDER BY node_name ASC")) {
      while ($row = $r->fetch_assoc()) $nodes[] = $row['node_name'];
    }
  }
  if (!$nodes) {
    echo json_encode([
      'ok'=>true,
      'nodes'=>[], 'available_metrics'=>['temperature','humidity','light'],
      'node'=>null, 'metric'=>($metric ?? 'temperature'),
      'labels'=>[], 'values'=>[],
      'points_shown'=>0, 'total_for_node'=>0,
      'avg_selected'=>0, 'avg_temperature'=>null, 'avg_humidity'=>null,
      'msg_count'=>0, 'last_time'=>null
    ]);
    exit;
  }

  // choose node if missing/invalid
  if (!$node || !in_array($node, $nodes, true)) $node = $nodes[0];

  // discover metrics (columns present)
  $candidates = ['temperature','humidity','light'];
  $have = [];
  if ($cols = $conn->query("SHOW COLUMNS FROM sensor_data")) {
    while ($c = $cols->fetch_assoc()) $have[] = $c['Field'];
  }
  $available_metrics = array_values(array_intersect($candidates, $have));
  if (!$available_metrics) $available_metrics = ['temperature','humidity','light']; // fallback display

  // choose metric if missing/invalid
  if (!$metric || !in_array($metric, $available_metrics, true)) $metric = $available_metrics[0];

  // time series for selected metric (skip NULLs)
  $labels=[]; $values=[];
  if (in_array($metric, $have, true)) {
    $st = $conn->prepare("
      SELECT time_received, $metric AS v
      FROM sensor_data
      WHERE node_name=? AND $metric IS NOT NULL
      ORDER BY time_received ASC
    ");
    $st->bind_param("s", $node);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) { $labels[]=$row['time_received']; $values[]=(float)$row['v']; }
    $st->close();
  }

  $total_for_node = count($values);
  if (count($labels) > $last) {
    $labels = array_slice($labels, -$last);
    $values = array_slice($values, -$last);
  }
  $points_shown = count($values);
  $avg_selected = $points_shown ? round(array_sum($values)/$points_shown, 2) : 0.0;

  // averages for temp/hum (only if those columns exist)
  $avg_temperature = null; $avg_humidity = null;
  if (in_array('temperature', $have, true)) {
    $st = $conn->prepare("SELECT AVG(temperature) a FROM sensor_data WHERE node_name=? AND temperature IS NOT NULL");
    $st->bind_param("s",$node); $st->execute();
    $avg_temperature = round((float)($st->get_result()->fetch_assoc()['a'] ?? 0), 2);
    $st->close();
  }
  if (in_array('humidity', $have, true)) {
    $st = $conn->prepare("SELECT AVG(humidity) a FROM sensor_data WHERE node_name=? AND humidity IS NOT NULL");
    $st->bind_param("s",$node); $st->execute();
    $avg_humidity = round((float)($st->get_result()->fetch_assoc()['a'] ?? 0), 2);
    $st->close();
  }

  // sensor_activity using your schema (one row per node)
  $msg_count = 0; $last_time = null;
  if ($conn->query("SHOW TABLES LIKE 'sensor_activity'")->num_rows) {
    $st = $conn->prepare("SELECT msg_count, last_time FROM sensor_activity WHERE node_name=? LIMIT 1");
    $st->bind_param("s", $node);
    $st->execute();
    if ($a = $st->get_result()->fetch_assoc()) {
      $msg_count = (int)$a['msg_count'];
      $last_time = $a['last_time'];
    }
    $st->close();
  }

  echo json_encode([
    'ok'=>true,
    'nodes'=>$nodes,
    'available_metrics'=>$available_metrics,
    'node'=>$node,
    'metric'=>$metric,
    'labels'=>$labels,
    'values'=>$values,
    'points_shown'=>$points_shown,
    'total_for_node'=>$total_for_node,
    'avg_selected'=>$avg_selected,
    'avg_temperature'=>$avg_temperature,
    'avg_humidity'=>$avg_humidity,
    'msg_count'=>$msg_count,
    'last_time'=>$last_time
  ]);
} catch (Throwable $e) {
  http_response_code(200);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
