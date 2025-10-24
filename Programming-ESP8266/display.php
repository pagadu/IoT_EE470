<?php
// display.php — overview page for nodes, data, and activity
require_once __DIR__ . "/config.php";

function qsel($conn, $sql, $types="", ...$params) {
  $st = $conn->prepare($sql);
  if ($types) $st->bind_param($types, ...$params);
  $st->execute();
  return $st->get_result();
}

try {
  $conn = db();
} catch (Throwable $e) {
  http_response_code(200);
  echo "<pre style='color:#ffb3b3;background:#241919;padding:12px;border-radius:10px'>
DB error: ".htmlspecialchars($e->getMessage())."
</pre>";
  exit;
}

$limit = isset($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : 50;
$metric = $_GET['metric'] ?? 'temperature';
$candidates = ['temperature','humidity','light'];
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM sensor_data");
while ($res && ($c = $res->fetch_assoc())) $cols[] = $c['Field'];
$available = array_values(array_intersect($candidates, $cols));
if (!$available) $available = ['temperature'];
if (!in_array($metric, $available, true)) $metric = $available[0];

// Registered nodes (preferred)
$nodes = [];
$r = $conn->query("SELECT node_name FROM sensor_register ORDER BY node_name ASC");
if ($r && $r->num_rows) {
  while ($row = $r->fetch_assoc()) $nodes[] = $row['node_name'];
} else {
  // Fallback to nodes seen in data
  $r = $conn->query("SELECT DISTINCT node_name FROM sensor_data ORDER BY node_name ASC");
  while ($r && ($row = $r->fetch_assoc())) $nodes[] = $row['node_name'];
}

$node = $_GET['node_name'] ?? ($nodes[0] ?? 'node_1');

// Node activity (your schema: msg_count/last_time)
$act = ['msg_count'=>0,'last_time'=>null];
$ra = qsel($conn, "SELECT msg_count, last_time FROM sensor_activity WHERE node_name=? LIMIT 1", "s", $node);
if ($ra && $ra->num_rows) $act = $ra->fetch_assoc();

// Recent data for selected node
$rows = [];
$rd = qsel($conn, "
  SELECT id, time_received, temperature, humidity, light
  FROM sensor_data
  WHERE node_name=?
  ORDER BY time_received DESC, id DESC
  LIMIT $limit
", "s", $node);
while ($rd && ($row = $rd->fetch_assoc())) $rows[] = $row;

// Averages for node (temp/hum)
$avgT = null; $avgH = null;
if (in_array('temperature',$available,true)) {
  $rt = qsel($conn,"SELECT AVG(temperature) a FROM sensor_data WHERE node_name=? AND temperature IS NOT NULL","s",$node);
  $avgT = round((float)($rt->fetch_assoc()['a'] ?? 0),2);
}
if (in_array('humidity',$available,true)) {
  $rh = qsel($conn,"SELECT AVG(humidity) a FROM sensor_data WHERE node_name=? AND humidity IS NOT NULL","s",$node);
  $avgH = round((float)($rh->fetch_assoc()['a'] ?? 0),2);
}

$chartUrl = "chart.php?node_name=".urlencode($node)."&metric=".urlencode($metric)."&last=".$limit;
$jsonUrl  = "api_node_json.php?node_name=".urlencode($node)."&metric=".urlencode($metric)."&last=".$limit;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>IoT Data — Display</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{--bg:#0b0b0b;--panel:#111;--muted:#9a9a9a;--accent:#00c800}
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,Segoe UI,Arial;color:#eee;background:var(--bg)}
  .wrap{max-width:1150px;margin:28px auto;padding:0 16px}
  .card{background:#111;border:1px solid #222;border-radius:14px;padding:16px;margin-bottom:16px}
  .subtle{color:#9a9a9a}
  .row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
  select, a.btn {background:#111;color:#eee;border:1px solid #222;padding:9px 12px;border-radius:10px;text-decoration:none;cursor:pointer;font-size:14px}
  a.btn.primary{outline:2px solid var(--accent)}
  table{width:100%;border-collapse:collapse;font-size:14px}
  th,td{border-bottom:1px solid #222;padding:8px 6px;text-align:left}
  th{color:#ccc;font-weight:600}
  .chip{background:#161616;border:1px solid #222;padding:6px 10px;border-radius:10px;display:inline-block}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <div class="row">
      <div class="chip">Registered nodes:</div>
      <form method="get" class="row" style="gap:8px">
        <label class="subtle">Node</label>
        <select name="node_name" onchange="this.form.submit()">
          <?php foreach ($nodes as $n): ?>
            <option value="<?=h($n)?>" <?=$n===$node?'selected':''?>><?=h($n)?></option>
          <?php endforeach ?>
        </select>

        <label class="subtle">Metric</label>
        <select name="metric" onchange="this.form.submit()">
          <?php foreach ($available as $m): ?>
            <option value="<?=h($m)?>" <?=$m===$metric?'selected':''?>><?=h($m)?></option>
          <?php endforeach ?>
        </select>

        <label class="subtle">Show last</label>
        <select name="limit" onchange="this.form.submit()">
          <?php foreach ([10,20,30,50,100,200,500] as $n): ?>
            <option value="<?=$n?>" <?=$n===$limit?'selected':''?>><?=$n?></option>
          <?php endforeach ?>
        </select>
        <noscript><button>Go</button></noscript>

        <a class="btn primary" href="<?=$chartUrl?>">Open Chart</a>
        <a class="btn" target="_blank" href="<?=$jsonUrl?>">JSON</a>
      </form>
    </div>

    <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap">
      <div class="chip">Node: <b><?=h($node)?></b></div>
      <div class="chip">Avg Temp: <b><?= ($avgT===null?'—':$avgT) ?></b></div>
      <div class="chip">Avg Hum: <b><?= ($avgH===null?'—':$avgH) ?></b></div>
      <div class="chip">Messages: <b><?= (int)$act['msg_count'] ?></b></div>
      <div class="chip">Last time: <b><?= h($act['last_time'] ?? '—') ?></b></div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h3 style="margin:0 0 8px 0;">Recent Data (<?=$limit?>)</h3>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Time</th>
            <th>Temperature</th>
            <th>Humidity</th>
            <th>Light</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="5" class="subtle">No rows yet for this node.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= h($r['time_received']) ?></td>
              <td><?= is_null($r['temperature']) ? '—' : h($r['temperature']) ?></td>
              <td><?= is_null($r['humidity'])    ? '—' : h($r['humidity']) ?></td>
              <td><?= is_null($r['light'])       ? '—' : h($r['light']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px 0;">All Nodes (activity)</h3>
      <table>
        <thead>
          <tr>
            <th>Node</th><th>Messages</th><th>Last Time</th><th>Chart</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $all = $conn->query("SELECT node_name, msg_count, last_time FROM sensor_activity ORDER BY node_name ASC");
          if ($all && $all->num_rows) {
            while ($a = $all->fetch_assoc()) {
              $cu = "chart.php?node_name=".urlencode($a['node_name'])."&metric=".urlencode($metric)."&last=".$limit;
              echo "<tr>
                      <td>".h($a['node_name'])."</td>
                      <td>".(int)$a['msg_count']."</td>
                      <td>".h($a['last_time'])."</td>
                      <td><a class='btn' href='$cu'>Open</a></td>
                    </tr>";
            }
          } else {
            echo "<tr><td colspan='4' class='subtle'>No activity yet.</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
