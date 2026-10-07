<?php
// filename: admin/profile.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
$allowed_roles = [ROLE_ADMIN];
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";
$page_title = 'Profile';
// Display the details of the administrator currently logged in.
$user_id = (int) $_SESSION['user_id'];
$user_result = mysqli_query($conn, "SELECT full_name, email FROM user WHERE user_id = $user_id");
$user = mysqli_fetch_assoc($user_result);
include "../includes/header.php";
?>
<section>
    <h1>Profile.</h1>
    <form class="panel profile-photo-form" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/profile_photo_process.php" enctype="multipart/form-data">
        <label>Profile photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required></label>
        <?php csrf_field(); ?>
        <button type="submit">Upload photo</button>
    </form>
    <div class="panel">
        <p><strong>Name</strong><br><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Email</strong><br><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</section>
<?php include "../includes/footer.php"; ?>

