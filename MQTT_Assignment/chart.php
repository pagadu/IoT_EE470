<?php
// ------------------------------------
// CONFIG
// ------------------------------------
$host = "srv738.hstgr.io";
$user = "u620326033_db_AlexPagadua";
$pass = "";
$db   = "u620326033_AlexPagaduan";

// ------------------------------------
// CONNECT TO DATABASE
// ------------------------------------
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// ------------------------------------
// GET DATA
// ------------------------------------
// FIX: use create_at, not timestamp
$sql = "SELECT value, create_at AS timestamp FROM mqtt_assignment ORDER BY id DESC LIMIT 50";
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "value" => (float)$row["value"],
        "timestamp" => $row["timestamp"]
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>MQTT Potentiometer Chart</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #0d1117;
            color: white;
            font-family: Arial;
            text-align: center;
            padding: 40px;
        }
        #chartContainer {
            width: 90%;
            max-width: 800px;
            margin: auto;
        }
    </style>
</head>
<body>

<h1>MQTT Potentiometer Live Chart</h1>

<div id="chartContainer">
    <canvas id="myChart"></canvas>
</div>

<script>
    const phpData = <?php echo json_encode($data); ?>;

    const labels = phpData.map(d => d.timestamp);
    const values = phpData.map(d => d.value);

    const ctx = document.getElementById("myChart").getContext("2d");

    new Chart(ctx, {
        type: "line",
        data: {
            labels: labels,
            datasets: [{
                label: "Potentiometer Value",
                data: values,
                borderWidth: 2,
                borderColor: "rgba(0, 200, 255, 1)",
                backgroundColor: "rgba(0, 200, 255, .2)",
                tension: .2
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>

</body>
</html>
