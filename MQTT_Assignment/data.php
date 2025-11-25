<?php
require_once "config.php";

$sql = "SELECT id, value, timestamp FROM mqtt_assignment ORDER BY id DESC LIMIT 50";
$result = $conn->query($sql);

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => $row["id"],
        "value" => floatval($row["value"]),
        "timestamp" => $row["timestamp"]
    ];
}

echo json_encode($data);
?>

