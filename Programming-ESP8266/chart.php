<?php require_once __DIR__ . "/config.php"; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>IoT Charts</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  :root{--bg:#0b0b0b;--panel:#111;--muted:#9a9a9a;--accent:#00c800}
  *{box-sizing:border-box} body{margin:0;font-family:system-ui,Segoe UI,Arial;color:#eee;background:var(--bg)}
  .wrap{max-width:1150px;margin:28px auto;padding:0 16px}
  .nav{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center}
  .nav a,.nav button,.nav select,.nav label.toggle{background:#111;color:#eee;border:1px solid #222;padding:9px 12px;border-radius:10px;text-decoration:none;cursor:pointer;font-size:14px}
  .nav a.active{outline:2px solid var(--accent)}
  .card{background:#111;border:1px solid #222;border-radius:14px;padding:16px;margin-bottom:16px}
  .subtle{color:#9a9a9a;font-size:13px}
  canvas{width:100%;max-height:520px}
  .stats{display:flex;gap:12px;margin:12px 0 8px;flex-wrap:wrap}
  .chip{background:#161616;border:1px solid #222;padding:6px 10px;border-radius:10px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media(max-width:900px){.grid{grid-template-columns:1fr}}
  .error{background:#241919;border:1px solid #552222;color:#ffb3b3;padding:10px;border-radius:10px;margin-bottom:12px;display:none}
</style>
</head>
<body>
<div class="wrap">

  <div id="err" class="error"></div>

  <div class="nav">
    <a href="display.php">← Tables</a>

    <label class="subtle">Node:</label>
    <select id="nodeSel"></select>

    <label class="subtle">Metric:</label>
    <select id="metricSel"></select>

    <label class="subtle">Last:</label>
    <select id="lastSel">
      <option>10</option><option>20</option><option>30</option>
      <option selected>50</option><option>100</option><option>200</option><option>500</option>
    </select>

    <a id="barBtn">Bar</a>
    <a id="lineBtn" class="active">Line</a>

    <label class="toggle"><input id="liveChk" type="checkbox" style="margin-right:6px"> Live</label>
    <button id="refreshBtn">Refresh now</button>
    <a id="jsonLink" target="_blank">JSON</a>
  </div>

  <div class="card">
    <div class="stats">
      <div class="chip">Node: <b id="statNode">—</b></div>
      <div class="chip">Metric: <b id="statMetric">—</b></div>
      <div class="chip">Points shown: <b id="statPoints">—</b></div>
      <div class="chip">Avg (selected): <b id="statAvgSel">—</b></div>
      <div class="chip">Avg Temp: <b id="statAvgT">—</b></div>
      <div class="chip">Avg Hum: <b id="statAvgH">—</b></div>
      <div class="chip">Messages: <b id="statMsgs">—</b></div>
      <div class="chip subtle">X = Time &nbsp;&nbsp; Y = <span id="yLabel">—</span></div>
    </div>
    <canvas id="chart"></canvas>
  </div>

  <div class="grid">
    <div class="card">
      <h3 style="margin:0 0 8px 0;">Node Activity</h3>
      <p class="subtle" id="lastTime">Last message: —</p>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px 0;">Notes</h3>
      <p class="subtle">Toggle <b>Live</b> for auto-refresh (10s). “Points shown” respects <b>Last N</b>.</p>
    </div>
  </div>

</div>

<script>
const qs = s=>document.querySelector(s);
const nodeSel=qs('#nodeSel'), metricSel=qs('#metricSel'), lastSel=qs('#lastSel');
const barBtn=qs('#barBtn'), lineBtn=qs('#lineBtn'), liveChk=qs('#liveChk');
const refreshBtn=qs('#refreshBtn'), jsonLink=qs('#jsonLink'), errBox=qs('#err');
const statNode=qs('#statNode'), statMetric=qs('#statMetric'), statPoints=qs('#statPoints');
const statAvgSel=qs('#statAvgSel'), statAvgT=qs('#statAvgT'), statAvgH=qs('#statAvgH'), statMsgs=qs('#statMsgs');
const yLabel=qs('#yLabel'), lastTime=qs('#lastTime');

let chartType='line'; let timer=null;

function showErr(msg){ errBox.style.display='block'; errBox.textContent=msg; }
function clearErr(){ errBox.style.display='none'; errBox.textContent=''; }

function apiUrl(){
  const u = new URL('api_node_json.php', window.location.href);
  u.searchParams.set('node_name', nodeSel.value || 'node_1');
  u.searchParams.set('metric', metricSel.value || 'temperature');
  u.searchParams.set('last', lastSel.value || '50');
  jsonLink.href = u.toString();
  return u.toString();
}

const ctx=document.getElementById('chart');
const chart=new Chart(ctx,{
  type:chartType,
  data:{labels:[],datasets:[
    {label:'',data:[],borderColor:'rgba(0,200,0,1)',backgroundColor:'rgba(0,200,0,0.25)',pointRadius:3,tension:0.2},
    {type:'line',label:'',data:[],borderColor:'rgba(0,200,0,.5)',borderDash:[6,6],pointRadius:0,fill:false}
  ]},
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{
      title:{display:true,text:''},
      legend:{labels:{color:'#e5e5e5'}},
      tooltip:{callbacks:{
        title:items=>'Time: '+items[0].label,
        label:item=> item.datasetIndex===0 ? (chart.data.datasets[0].label+': '+item.formattedValue) : chart.data.datasets[1].label
      }}
    },
    scales:{
      x:{ticks:{color:'#bdbdbd',maxRotation:45,minRotation:0},grid:{color:'#1f1f1f'}},
      y:{ticks:{color:'#bdbdbd'},grid:{color:'#1f1f1f'},title:{display:true,text:''}}
    }
  }
});

// Attach listeners immediately (even if first refresh fails)
barBtn.addEventListener('click',()=>{chartType='bar';barBtn.classList.add('active');lineBtn.classList.remove('active');chart.config.type='bar';chart.update();});
lineBtn.addEventListener('click',()=>{chartType='line';lineBtn.classList.add('active');barBtn.classList.remove('active');chart.config.type='line';chart.update();});
nodeSel.addEventListener('change', refresh);
metricSel.addEventListener('change', refresh);
lastSel.addEventListener('change', refresh);
liveChk.addEventListener('change', ()=>{ if(timer){clearInterval(timer);timer=null;} if(liveChk.checked){ timer=setInterval(refresh,10000); } });
refreshBtn.addEventListener('click', refresh);

async function refresh(){
  try{
    clearErr();
    const r=await fetch(apiUrl(),{cache:'no-store'});
    const j=await r.json();
    if(!j.ok){ showErr(j.error||'API error'); return; }

    // Build dropdowns once
    if(!nodeSel.options.length){
      (j.nodes && j.nodes.length ? j.nodes : ['node_1','node_2']).forEach(n=>{
        const o=document.createElement('option'); o.value=o.text=n; nodeSel.add(o);
      });
      if(j.node) nodeSel.value=j.node;
    }
    if(!metricSel.options.length){
      (j.available_metrics && j.available_metrics.length ? j.available_metrics : ['temperature','humidity','light']).forEach(m=>{
        const o=document.createElement('option'); o.value=o.text=m; metricSel.add(o);
      });
      if(j.metric) metricSel.value=j.metric;
    }

    statNode.textContent=j.node ?? '—';
    statMetric.textContent=j.metric ?? '—';
    statPoints.textContent=j.points_shown ?? '0';
    statAvgSel.textContent=(j.avg_selected ?? '—');
    statAvgT.textContent=(j.avg_temperature ?? '—');
    statAvgH.textContent=(j.avg_humidity ?? '—');
    statMsgs.textContent=j.msg_count ?? '0';
    lastTime.textContent='Last message: ' + (j.last_time ?? '—');
    yLabel.textContent=(j.metric ? j.metric.charAt(0).toUpperCase()+j.metric.slice(1) : '—');

    chart.options.plugins.title.text = `Sensor Node ${j.node ?? ''} — ${(j.metric ?? '').toString().charAt(0).toUpperCase()+(j.metric ?? '').toString().slice(1)} vs Time`;
    chart.options.scales.y.title.text = j.metric ?? '';
    chart.config.type = chartType;
    chart.data.labels = j.labels ?? [];
    chart.data.datasets[0].label = `${j.metric ?? ''} (${j.points_shown ?? 0} pts)`;
    chart.data.datasets[0].data  = j.values ?? [];
    const avg = Number(j.avg_selected || 0);
    chart.data.datasets[1].label = `Average (${isFinite(avg)?avg:'0'})`;
    chart.data.datasets[1].data  = (j.values ?? []).map(()=>isFinite(avg)?avg:0);
    chart.update('none');
  } catch(e){
    showErr(String(e));
  }
}

// Initial load + live
refresh();
</script>
</body>
</html>
