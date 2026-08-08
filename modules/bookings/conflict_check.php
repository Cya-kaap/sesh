<?php

/**
 * Check whether a booking conflicts with another
 * pending or approved booking for the same room.
 */
function hasBookingConflict(
    PDO $pdo,
    int $room_id,
    string $date,
    string $start_time,
    string $end_time,
    ?int $exclude_booking_id = null
): bool {

    $sql = "
        SELECT COUNT(*)
        FROM bookings
        WHERE room_id = :room_id
          AND date = :date
          AND status IN ('pending', 'approved')
          AND start_time < :end_time
          AND end_time > :start_time
    ";

    $params = [
        ':room_id'   => $room_id,
        ':date'      => $date,
        ':start_time' => $start_time,
        ':end_time'   => $end_time
    ];

    if ($exclude_booking_id !== null) {
        $sql .= " AND id != :exclude_id";
        $params[':exclude_id'] = $exclude_booking_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn() > 0;
}


/**
 * Return conflicting bookings.
 */
function getConflictingBookings(
    PDO $pdo,
    int $room_id,
    string $date,
    string $start_time,
    string $end_time,
    ?int $exclude_booking_id = null
): array {

    $sql = "
        SELECT 
            b.id,
            b.date,
            b.start_time,
            b.end_time,
            b.type,
            b.status,
            u.full_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.room_id = :room_id
          AND b.date = :date
          AND b.status IN ('pending', 'approved')
          AND b.start_time < :end_time
          AND b.end_time > :start_time
    ";

    $params = [
        ':room_id'    => $room_id,
        ':date'       => $date,
        ':start_time' => $start_time,
        ':end_time'   => $end_time
    ];

    if ($exclude_booking_id !== null) {
        $sql .= " AND b.id != :exclude_id";
        $params[':exclude_id'] = $exclude_booking_id;
    }

    $sql .= " ORDER BY b.start_time";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}