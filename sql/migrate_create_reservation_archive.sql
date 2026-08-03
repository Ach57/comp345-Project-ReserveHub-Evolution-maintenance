CREATE TABLE IF NOT EXISTS ReservationArchive (
    archive_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_booking_id INT NOT NULL,
    customer_id INT NOT NULL,
    restaurant_id INT DEFAULT NULL,
    table_id INT DEFAULT NULL,

    restaurant_name VARCHAR(255) DEFAULT NULL,
    table_number VARCHAR(20) DEFAULT NULL,

    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    guest_count INT NOT NULL DEFAULT 1,
    special_requests TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'completed',

    reservation_created_at TIMESTAMP NULL DEFAULT NULL,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archive_reason VARCHAR(100) NOT NULL DEFAULT 'completed',

    PRIMARY KEY (archive_id),
    UNIQUE KEY uq_reservation_archive_source_booking (source_booking_id),
    KEY idx_reservation_archive_customer_date (customer_id, reservation_date),
    KEY idx_reservation_archive_restaurant_date (restaurant_id, reservation_date),
    KEY idx_reservation_archive_status (status),
    KEY idx_reservation_archive_archived_at (archived_at)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ReservationArchive (
    source_booking_id,
    customer_id,
    restaurant_id,
    table_id,
    restaurant_name,
    table_number,
    reservation_date,
    reservation_time,
    guest_count,
    special_requests,
    status,
    reservation_created_at,
    archive_reason
)
SELECT
    r.booking_id,
    r.customer_id,
    r.restaurant_id,
    r.table_id,
    rest.name,
    t.table_number,
    r.date,
    r.reservation_time,
    r.guest_count,
    r.special_requests,
    r.status,
    r.created_at,
    'completed'
FROM reservations r
LEFT JOIN restaurants rest
    ON rest.id = r.restaurant_id
LEFT JOIN tables t
    ON t.table_id = r.table_id
WHERE r.status = 'completed';