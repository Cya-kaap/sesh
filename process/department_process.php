<?php
// filename: process/department_process.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
$allowed_roles = [ROLE_ADMIN];
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/departments.php');
    exit;
}
verify_csrf();
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

if ($action === 'delete') {
    $department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
    mysqli_query($conn, "DELETE FROM department WHERE department_id = $department_id");
    flash('success', 'Department deleted.');
    header('Location: ' . BASE_URL . '/admin/departments.php');
    exit;
}

$name = isset($_POST['department_name']) ? trim((string) $_POST['department_name']) : '';
$name_sql = mysqli_real_escape_string($conn, $name);
mysqli_query($conn, "INSERT INTO department (department_name) VALUES ('$name_sql')");
flash('success', 'Department added.');
header('Location: ' . BASE_URL . '/admin/departments.php');
exit;
