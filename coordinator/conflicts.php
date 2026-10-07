<?php // filename: coordinator/conflicts.php

/*
 * Conflicts is intentionally read-only. A report page must not approve or
 * reject rows because mixing display and mutation makes auditing difficult.
 */
require "../includes/auth_check.php";
$required_role = ROLE_COORDINATOR;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";

/*
 * The subqueries expose both kinds of conflict: an approved collision and
 * multiple pending requests for the same room, date, and timeslot.
 */
$approved_sql = db_escape($conn, STATUS_APPROVED);
$pending_sql = db_escape($conn, STATUS_PENDING);
$query = "SELECT b.booking_id, b.booking_date, b.purpose, u.full_name,
			r.room_number, t.slot_name,
			EXISTS (
				SELECT 1 FROM booking AS approved
				WHERE approved.booking_id <> b.booking_id
				AND approved.room_id = b.room_id
				AND approved.booking_date = b.booking_date
				AND approved.slot_id = b.slot_id
				AND approved.status = '$approved_sql'
			) AS has_approved_conflict,
			(
				SELECT COUNT(*) FROM booking AS other_pending
				WHERE other_pending.booking_id <> b.booking_id
				AND other_pending.room_id = b.room_id
				AND other_pending.booking_date = b.booking_date
				AND other_pending.slot_id = b.slot_id
				AND other_pending.status = '$pending_sql'
			) AS pending_conflict_count
	 FROM booking AS b
	 INNER JOIN user AS u ON u.user_id = b.faculty_id
	 INNER JOIN room AS r ON r.room_id = b.room_id
	 INNER JOIN timeslot AS t ON t.slot_id = b.slot_id
	 WHERE b.status = '$pending_sql'
	 AND (
		EXISTS (
			SELECT 1 FROM booking AS approved_filter
			WHERE approved_filter.booking_id <> b.booking_id
			AND approved_filter.room_id = b.room_id
			AND approved_filter.booking_date = b.booking_date
			AND approved_filter.slot_id = b.slot_id
			AND approved_filter.status = '$approved_sql'
		)
		OR EXISTS (
			SELECT 1 FROM booking AS pending_filter
			WHERE pending_filter.booking_id <> b.booking_id
			AND pending_filter.room_id = b.room_id
			AND pending_filter.booking_date = b.booking_date
			AND pending_filter.slot_id = b.slot_id
			AND pending_filter.status = '$pending_sql'
		)
	 )
	 ORDER BY b.booking_date, t.slot_id, r.room_number";
$conflicts = mysqli_query($conn, $query);

$page_title = 'Conflicts';
require "../includes/header.php";
?>
<section>
	<h1>Scheduling conflicts.</h1>
	<p class="muted">Review collisions before approving pending requests.</p>

	<div class="table-wrap">
		<table border="1" cellpadding="5" cellspacing="0">
			<tr>
				<th>Date</th>
				<th>Room</th>
				<th>Period</th>
				<th>Faculty</th>
				<th>Conflict</th>
			</tr>
			<?php if (mysqli_num_rows($conflicts) > 0): ?>
				<?php while ($conflict = mysqli_fetch_assoc($conflicts)): ?>
					<tr>
						<td><?php echo htmlspecialchars($conflict['booking_date'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($conflict['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($conflict['slot_name'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($conflict['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td>
							<?php if ((int) $conflict['has_approved_conflict'] === 1): ?>
								Approved booking already exists
							<?php else: ?>
								<?php echo (int) $conflict['pending_conflict_count'] + 1; ?> pending requests overlap
							<?php endif; ?>
						</td>
					</tr>
				<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="5" class="muted">No scheduling conflicts found.</td></tr>
			<?php endif; ?>
		</table>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
