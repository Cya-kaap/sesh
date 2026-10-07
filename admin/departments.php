<?php
// filename: admin/departments.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
$allowed_roles = [ROLE_ADMIN];
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/csrf.php";
$page_title = 'Departments';
// Load departments for the list shown below the add form.
$departments = mysqli_query($conn, 'SELECT department_id, department_name FROM department ORDER BY department_name');
include "../includes/header.php";
?>
<section>
    <h1>Departments.</h1>
    <form class="panel" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/department_process.php">
        <input type="hidden" name="action" value="save">
        <label>Department name<input name="department_name" required></label>
        <?php csrf_field(); ?>
        <button>Add department</button>
    </form>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Name</th>
            <th>Action</th>
        </tr>
        <?php if (mysqli_num_rows($departments) > 0): ?>
        <?php while ($d = mysqli_fetch_assoc($departments)): ?>
            <tr>
                <td><?php echo htmlspecialchars($d['department_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <form class="inline" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/department_process.php">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="department_id" value="<?php echo (int) $d['department_id']; ?>">
                        <button class="button alt" onclick="return confirm('Delete this department?')">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="2">No departments found.</td></tr>
        <?php endif; ?>
    </table>
</section>
<?php include "../includes/footer.php"; ?>

