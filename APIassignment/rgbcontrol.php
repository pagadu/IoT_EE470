<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>ESP LED + RGB Control</title>
<style>
body{
  font-family:'Arial Narrow', Arial, sans-serif;
  background:#0d1117;
  color:#e6edf3;
  text-align:center;
  padding:40px;
}
.card{
  background:#161b22;
  padding:30px;
  border-radius:14px;
  box-shadow:0 0 25px rgba(56,139,253,0.2);
  max-width:600px;
  margin:auto;
}
h1{
  color:#58a6ff;
  margin-bottom:10px;
  font-weight:bold;
}
.section{margin-top:25px;}
.btn{
  background:#238636;
  color:white;
  border:none;
  padding:10px 25px;
  margin:6px;
  border-radius:8px;
  cursor:pointer;
  font-size:15px;
}
.btn.off{background:#da3633;}
.btn:hover{
  opacity:.85;
  transform:scale(1.05);
}
.slider{width:80%;margin:10px auto;}
output{display:block;margin-bottom:5px;}
</style>
</head>
<body>
<div class="card">
  <h1>ESP LED + RGB Control</h1>

  <!-- LED CONTROL -->
  <div class="section">
    <h2>Main LED Control</h2>
    <a href="?led=on"><button class="btn">LED ON</button></a>
    <a href="?led=off"><button class="btn off">LED OFF</button></a>
  </div>

  <!-- RGB CONTROL -->
  <div class="section">
    <h2>RGB Control (0 – 255)</h2>
    <form method="post">
      <label>Red</label>
      <input type="range" name="r" min="0" max="255" class="slider"
             value="<?php echo file_exists('red.txt')?file_get_contents('red.txt'):0;?>"
             oninput="this.nextElementSibling.value=this.value">
      <output><?php echo file_exists('red.txt')?file_get_contents('red.txt'):0;?></output>

      <label>Green</label>
      <input type="range" name="g" min="0" max="255" class="slider"
             value="<?php echo file_exists('green.txt')?file_get_contents('green.txt'):0;?>"
             oninput="this.nextElementSibling.value=this.value">
      <output><?php echo file_exists('green.txt')?file_get_contents('green.txt'):0;?></output>

      <label>Blue</label>
      <input type="range" name="b" min="0" max="255" class="slider"
             value="<?php echo file_exists('blue.txt')?file_get_contents('blue.txt'):0;?>"
             oninput="this.nextElementSibling.value=this.value">
      <output><?php echo file_exists('blue.txt')?file_get_contents('blue.txt'):0;?></output>

      <br><input type="submit" class="btn" value="Save RGB Values">
    </form>
  </div>

<?php
// == HANDLE LED ==
if(isset($_GET['led'])){
  $state = ($_GET['led']=='on') ? 'on' : 'off';
  file_put_contents('results.txt', $state);
  echo "<p>LED turned $state</p>";
}

// == HANDLE RGB ==
if($_SERVER["REQUEST_METHOD"]=="POST"){
  $r = intval($_POST["r"]);
  $g = intval($_POST["g"]);
  $b = intval($_POST["b"]);
  file_put_contents("red.txt", $r);
  file_put_contents("green.txt", $g);
  file_put_contents("blue.txt", $b);

  // Redirect to reload page cleanly and prevent POST refresh issues
  header("Location: rgbcontrol.php?saved=1&r=$r&g=$g&b=$b");
  exit();
}

// == DISPLAY SAVED CONFIRMATION ==
if(isset($_GET['saved'])){
  $r = intval($_GET['r']);
  $g = intval($_GET['g']);
  $b = intval($_GET['b']);
  echo "<p>Saved RGB → R:$r G:$g B:$b</p>";
}
?>
</div>
</body>
</html>
