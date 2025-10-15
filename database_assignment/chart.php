<?php
// chart.php
require_once __DIR__ . "/config.php";
$conn = db();

/* -------- params -------- */
$node = $_GET['node_name'] ?? 'node_1';
$type = $_GET['type']      ?? 'bar';     // 'bar' or 'line'
$last = isset($_GET['last']) ? (int)$_GET['last'] : 50;  // how many latest points to show
$last = max(5, min($last, 500)); // safety

/* -------- data -------- */
$sql = "SELECT time_received, temperature 
        FROM sensor_data 
        WHERE node_name=? 
        ORDER BY time_received ASC";
$st  = $conn->prepare($sql);
$st->bind_param("s", $node);
$st->execute();
$res = $st->get_result();

$labels = []; $temps = [];
while ($r = $res->fetch_assoc()) {
    $labels[] = $r['time_received'];
    $temps[]  = (float)$r['temperature'];
}
$st->close();

/* Keep only the last N points (if there are many) */
if (count($labels) > $last) {
    $labels = array_slice($labels, -$last);
    $temps  = array_slice($temps,  -$last);
}

/* quick stats */
$avg = count($temps) ? array_sum($temps)/count($temps) : 0;
$avg = round($avg, 2);

/* link helpers */
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$base  = 'chart.php?node_name='.urlencode($node);
$jsonU = 'api_node_json.php?node_name='.urlencode($node);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sensor Node <?=h($node)?> — Temperature vs Time</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  :root{
    --bg:#0b0b0b; --panel:#111; --muted:#7c7c7c; --green:#00c800; --greenSoft: rgba(0,200,0,.25);
  }
  body{margin:0; font-family:system-ui,Segoe UI,Arial; color:#eee; background:var(--bg)}
  .wrap{max-width:1100px; margin:28px auto; padding:0 16px}
  .nav{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px}
  .nav a, .nav button, select{
    background:var(--panel); color:#eee; border:1px solid #222; padding:9px 12px; border-radius:10px;
    text-decoration:none; cursor:pointer; font-size:14px
  }
  .nav a.active, .nav button.active{ outline:2px solid var(--green) }
  .card{background:var(--panel); border:1px solid #222; border-radius:14px; padding:16px}
  .subtle{color:var(--muted); font-size:13px}
  canvas{width:100%; max-height:520px}
  .stats{display:flex; gap:18px; margin:12px 0 4px}
  .chip{background:#161616; border:1px solid #222; padding:6px 10px; border-radius:10px}
</style>
</head>
<body>
<div class="wrap">

  <div class="nav">
    <a href="display.php">← Tables</a>

    <!-- node switch -->
    <form method="get" style="display:flex; gap:8px; align-items:center">
      <input type="hidden" name="type" value="<?=h($type)?>">
      <label class="subtle">Node:</label>
      <select name="node_name" onchange="this.form.submit()">
        <?php
        // build node list quickly
        $rs = $conn->query("SELECT DISTINCT node_name FROM sensor_data ORDER BY node_name ASC");
        while ($row = $rs->fetch_assoc()):
          $sel = ($row['node_name']===$node)?'selected':'';
        ?>
          <option <?=$sel?>><?=h($row['node_name'])?></option>
        <?php endwhile ?>
      </select>

      <label class="subtle">Last:</label>
      <select name="last" onchange="this.form.submit()">
        <?php foreach([10,20,30,50,100,200,500] as $n): ?>
          <option value="<?=$n?>" <?=$n===$last?'selected':''?>><?=$n?></option>
        <?php endforeach ?>
      </select>
      <noscript><button>Go</button></noscript>
    </form>

    <!-- bar / line toggles -->
    <a class="<?=$type==='bar'?'active':''?>"  href="<?=$base.'&type=bar&last='.$last?>">Bar</a>
    <a class="<?=$type==='line'?'active':''?>" href="<?=$base.'&type=line&last='.$last?>">Line</a>

    <!-- JSON -->
    <a href="<?=$jsonU?>" target="_blank">JSON</a>
  </div>

  <div class="card">
    <div class="stats">
      <div class="chip">Node: <b><?=h($node)?></b></div>
      <div class="chip">Points: <b><?=count($temps)?></b></div>
      <div class="chip">Average: <b><?=$avg?> °C</b></div>
      <div class="chip subtle">X = Time &nbsp;&nbsp; Y = Temperature (°C)</div>
    </div>

    <canvas id="chart"></canvas>
  </div>

</div>

<script>
const labels = <?=json_encode($labels)?>;
const temps  = <?=json_encode($temps)?>;
const avg    = <?=json_encode($avg)?>;
const avgLine = temps.map(() => avg);

const ctx = document.getElementById('chart');
const chart = new Chart(ctx, {
  type: <?=json_encode($type)?>,
  data: {
    labels,
    datasets: [
      {
        label: 'Temperature (°C)',
        data: temps,
        borderColor: 'rgba(0,200,0,1)',
        backgroundColor: 'rgba(0,200,0,0.25)',
        pointRadius: <?= $type==='line' ? 3 : 0 ?>,
        tension: 0.2
      },
      // average line (always a line)
      {
        type: 'line',
        label: 'Average (<?=$avg?> °C)',
        data: avgLine,
        borderColor: 'rgba(0,200,0,.5)',
        borderDash: [6,6],
        pointRadius: 0,
        fill: false
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: {
        display: true,
        text: 'Sensor Node <?=h($node)?> — Temperature vs Time'
      },
      legend: { labels: { color: '#e5e5e5' } },
      tooltip: {
        callbacks: {
          title: (items) => 'Time: ' + items[0].label,
          label: (item) => 'Temp: ' + item.formattedValue + ' °C'
        }
      }
    },
    scales: {
      x: { ticks: { color:'#bdbdbd', maxRotation: 45, minRotation: 0 }, grid:{color:'#1f1f1f'} },
      y: { ticks: { color:'#bdbdbd' }, grid:{color:'#1f1f1f'}, title:{display:true, text:'°C'} }
    }
  }
});
</script>
</body>
</html>
