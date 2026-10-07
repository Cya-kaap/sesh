<?php
// filename: process/asset_process.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/assets.php');
    exit;
}

verify_csrf();

// The form groups values by room: assets[room_id][asset_type].
$submitted_assets = isset($_POST['assets']) ? $_POST['assets'] : [];
$asset_types = ['Projector' => 'projector', 'AC' => 'ac', 'Whiteboard' => 'whiteboard'];

// The live schema does not have a unique room/type key, so find each row first.
foreach ($submitted_assets as $room_id => $values) {
    $room_id = (int) $room_id;
    if ($room_id < 1 || !is_array($values)) {
        continue;
    }

    foreach ($asset_types as $asset_type => $field) {
        $quantity = max(0, (int) (isset($values[$field]) ? $values[$field] : 0));
        $asset_type_sql = mysqli_real_escape_string($conn, $asset_type);
        $find_query = "SELECT asset_id FROM asset WHERE room_id = $room_id AND asset_type = '$asset_type_sql'";
        $find_result = mysqli_query($conn, $find_query);
        $asset = mysqli_fetch_assoc($find_result);

        if ($asset !== null) {
            // Update the existing record instead of creating a duplicate.
            $asset_id = (int) $asset['asset_id'];
            mysqli_query($conn, "UPDATE asset SET quantity = $quantity WHERE asset_id = $asset_id");
        } else {
            // Some rooms may not have a row for every supported asset type yet.
            $insert_query = "INSERT INTO asset (room_id, asset_type, quantity) VALUES ($room_id, '$asset_type_sql', $quantity)";
            mysqli_query($conn, $insert_query);
        }
    }
}

flash('success', 'Assets updated.');
header('Location: ' . BASE_URL . '/admin/assets.php');
exit;
