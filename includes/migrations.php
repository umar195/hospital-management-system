<?php
/**
 * Schema upgrade helpers (v1 -> v2).
 *
 * The upgrade adds the doctor consultation / queue / follow-up workflow on top
 * of the original laboratory-centric schema. Every step checks the current
 * structure first, so the upgrade is idempotent and never touches existing data.
 */

/** True when a table exists in the current database. */
function hmsTableExists(PDO $pdo, $table)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

/** True when a column exists on a table in the current database. */
function hmsColumnExists(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Apply all v2 schema changes. Returns an array of log lines.
 * Existing records are always preserved.
 */
function runSchemaUpgrades(PDO $pdo)
{
    $log = [];
    $apply = function ($condition, $sql, $message) use ($pdo, &$log) {
        if ($condition) {
            $pdo->exec($sql);
            $log[] = $message;
        }
    };

    // ---------------------------------------------------------- appointments
    $apply(!hmsColumnExists($pdo, 'appointments', 'duration_minutes'),
        'ALTER TABLE appointments ADD COLUMN duration_minutes INT DEFAULT 30 AFTER appointment_time',
        'appointments: added duration_minutes');
    $apply(!hmsColumnExists($pdo, 'appointments', 'appointment_type'),
        "ALTER TABLE appointments ADD COLUMN appointment_type ENUM('New Consultation','Follow-Up','General Checkup','Lab Review','Emergency','Other') DEFAULT 'New Consultation' AFTER duration_minutes",
        'appointments: added appointment_type');
    $apply(!hmsColumnExists($pdo, 'appointments', 'referral_source'),
        'ALTER TABLE appointments ADD COLUMN referral_source VARCHAR(100) NULL AFTER purpose',
        'appointments: added referral_source');
    $apply(!hmsColumnExists($pdo, 'appointments', 'visit_id'),
        'ALTER TABLE appointments ADD COLUMN visit_id INT NULL AFTER referral_source',
        'appointments: added visit_id (link to the consultation created on check-in)');

    // Expand the status list while keeping the legacy value, migrate the data,
    // then drop the legacy value from the definition.
    $col = fetchOne("SELECT COLUMN_TYPE AS t FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = 'appointments' AND column_name = 'status'");
    if ($col && strpos($col['t'], "'Waiting'") === false) {
        $pdo->exec("ALTER TABLE appointments MODIFY status ENUM('Scheduled','Confirmed','Checked In','Waiting','In Consultation','Completed','Cancelled','No Show','Arrived') DEFAULT 'Scheduled'");
        $pdo->exec("UPDATE appointments SET status = 'Checked In' WHERE status = 'Arrived'");
        $pdo->exec("ALTER TABLE appointments MODIFY status ENUM('Scheduled','Confirmed','Checked In','Waiting','In Consultation','Completed','Cancelled','No Show') DEFAULT 'Scheduled'");
        $log[] = "appointments: extended status workflow (existing 'Arrived' records became 'Checked In')";
    }

    // ---------------------------------------------------------------- visits
    $visitColumns = [
        'appointment_id'            => 'INT NULL AFTER doctor_id',
        'follow_up_of_visit_id'     => 'INT NULL AFTER appointment_id',
        'token_number'              => 'INT NULL AFTER follow_up_of_visit_id',
        'queue_status'              => "ENUM('Waiting','Called','In Consultation','Completed','Cancelled','No Show') NULL AFTER token_number",
        'chief_complaint'           => 'TEXT NULL AFTER symptoms',
        'temperature'               => 'VARCHAR(10) NULL AFTER chief_complaint',
        'blood_pressure'            => 'VARCHAR(15) NULL AFTER temperature',
        'pulse'                     => 'VARCHAR(10) NULL AFTER blood_pressure',
        'respiratory_rate'          => 'VARCHAR(10) NULL AFTER pulse',
        'oxygen_saturation'         => 'VARCHAR(10) NULL AFTER respiratory_rate',
        'weight'                    => 'VARCHAR(10) NULL AFTER oxygen_saturation',
        'height'                    => 'VARCHAR(10) NULL AFTER weight',
        'examination_notes'         => 'TEXT NULL AFTER diagnosis',
        'doctor_notes'              => 'TEXT NULL AFTER examination_notes',
        'consultation_fee'          => 'DECIMAL(10,2) DEFAULT 0 AFTER doctor_notes',
        'checked_in_at'             => 'DATETIME NULL',
        'consultation_started_at'   => 'DATETIME NULL',
        'consultation_completed_at' => 'DATETIME NULL',
    ];
    foreach ($visitColumns as $column => $definition) {
        $apply(!hmsColumnExists($pdo, 'visits', $column),
            "ALTER TABLE visits ADD COLUMN $column $definition",
            "visits: added $column");
    }

    // --------------------------------------------------------------- doctors
    $apply(!hmsColumnExists($pdo, 'doctors', 'consultation_fee'),
        'ALTER TABLE doctors ADD COLUMN consultation_fee DECIMAL(10,2) DEFAULT 0 AFTER license_number',
        'doctors: added consultation_fee');

    // --------------------------------------------------------- prescriptions
    $apply(!hmsColumnExists($pdo, 'prescriptions', 'doctor_id'),
        'ALTER TABLE prescriptions ADD COLUMN doctor_id INT NULL AFTER visit_id',
        'prescriptions: added doctor_id');
    $apply(!hmsColumnExists($pdo, 'prescriptions', 'diagnosis'),
        'ALTER TABLE prescriptions ADD COLUMN diagnosis TEXT NULL AFTER prescription_date',
        'prescriptions: added diagnosis');
    $apply(!hmsColumnExists($pdo, 'prescription_items', 'route'),
        'ALTER TABLE prescription_items ADD COLUMN route VARCHAR(50) NULL AFTER duration',
        'prescription_items: added route');

    // ----------------------------------------------------------- test orders
    $apply(!hmsColumnExists($pdo, 'test_orders', 'doctor_id'),
        'ALTER TABLE test_orders ADD COLUMN doctor_id INT NULL AFTER visit_id',
        'test_orders: added doctor_id (ordering doctor)');

    // ------------------------------------------------------------ follow ups
    if (!hmsTableExists($pdo, 'follow_ups')) {
        $pdo->exec("CREATE TABLE follow_ups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            doctor_id INT NULL,
            visit_id INT NULL,
            new_visit_id INT NULL,
            follow_up_date DATE NOT NULL,
            follow_up_time TIME NULL,
            follow_up_type ENUM('Routine Follow-Up','Test Result Review','Medication Review','Post-Treatment Review','Chronic Care Follow-Up','Other') DEFAULT 'Routine Follow-Up',
            reason VARCHAR(255) NULL,
            notes TEXT NULL,
            status ENUM('Scheduled','Completed','Missed','Cancelled') DEFAULT 'Scheduled',
            reschedule_history TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_followup_date (follow_up_date),
            INDEX idx_followup_status (status),
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
            FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
            FOREIGN KEY (new_visit_id) REFERENCES visits(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = 'follow_ups: table created';
    }

    return $log;
}
