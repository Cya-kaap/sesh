<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db.php';

requireRole(['Faculty', 'Coordinator', 'Admin']);

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$canViewAll = in_array($role, ['Admin', 'Coordinator'], true);

$viewAll = $canViewAll && isset($_GET['all']);

if ($viewAll) {

    $stmt = $pdo->query("
        SELECT
            b.*,
            r.name AS room_name,
            u.full_name,
            p.name AS programme_name,
            s.number AS semester_number
        FROM bookings b
        JOIN rooms r
            ON b.room_id = r.id
        JOIN users u
            ON b.user_id = u.id
        JOIN programmes p
            ON b.programme_id = p.id
        JOIN semesters s
            ON b.semester_id = s.id
        ORDER BY b.date DESC, b.start_time DESC
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT
            b.*,
            r.name AS room_name,
            p.name AS programme_name,
            s.number AS semester_number
        FROM bookings b
        JOIN rooms r
            ON b.room_id = r.id
        JOIN programmes p
            ON b.programme_id = p.id
        JOIN semesters s
            ON b.semester_id = s.id
        WHERE b.user_id = :user_id
        ORDER BY b.date DESC, b.start_time DESC
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);
}

$bookings = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $viewAll ? 'All Bookings' : 'My Bookings' ?> | SESH</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

</head>

<body>

<div class="container">

    <h1>
        <?= $viewAll ? 'All Bookings' : 'My Bookings' ?>
    </h1>

    <p>

        <a href="create.php">
            + New Booking
        </a>

        |

        <?php if ($canViewAll && !$viewAll): ?>

            <a href="list.php?all=1">
                View All Bookings
            </a>

            |

        <?php endif; ?>

        <?php if ($role === 'Admin'): ?>

            <a href="../../admin/">
                ← Admin Dashboard
            </a>

        <?php elseif ($role === 'Coordinator'): ?>

            <a href="../../coordinator/">
                ← Coordinator Dashboard
            </a>

        <?php else: ?>

            <a href="../../faculty/">
                ← Faculty Dashboard
            </a>

        <?php endif; ?>

    </p>


    <?php if (isset($_GET['success'])): ?>

        <div class="success-message">
            Booking request submitted successfully.
        </div>

    <?php endif; ?>


    <?php if (empty($bookings)): ?>

        <p>No bookings found.</p>

    <?php else: ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>ID</th>
                        <th>Room</th>
                        <th>Programme</th>
                        <th>Semester</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Status</th>

                        <?php if ($viewAll): ?>
                            <th>Requested By</th>
                        <?php endif; ?>

                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($bookings as $booking): ?>

                    <tr>

                        <td>
                            <?= (int)$booking['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($booking['room_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($booking['programme_name']) ?>
                        </td>

                        <td>
                            Semester <?= (int)$booking['semester_number'] ?>
                        </td>

                        <td>
                            <?= date('M j, Y', strtotime($booking['date'])) ?>
                        </td>

                        <td>
                            <?= date('g:i A', strtotime($booking['start_time'])) ?>
                            -
                            <?= date('g:i A', strtotime($booking['end_time'])) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($booking['type']) ?>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                            </strong>
                        </td>

                        <?php if ($viewAll): ?>

                            <td>
                                <?= htmlspecialchars($booking['full_name']) ?>
                            </td>

                        <?php endif; ?>

                        <td>

                            <?php if (
                                $booking['status'] === 'pending'
                                && (int)$booking['user_id'] === $user_id
                            ): ?>

                                <a href="edit.php?id=<?= (int)$booking['id'] ?>">
                                    Edit
                                </a>

                                |

                                <a
                                    href="delete.php?id=<?= (int)$booking['id'] ?>"
                                    onclick="return confirm('Cancel this booking request?')"
                                >
                                    Cancel
                                </a>

                            <?php endif; ?>


                            <?php if (
                                $canViewAll
                                && $booking['status'] === 'pending'
                            ): ?>

                                <a href="approve.php?id=<?= (int)$booking['id'] ?>">
                                    Review
                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

</body>

</html>