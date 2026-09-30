-- GradScan migration: record the school and graduation year on each scan.
--
-- scan_log used to reach a school only through qr_code -> graduate, so a
-- deleted graduate's scans disappeared from their school's history, and scans
-- could not be filtered by graduation year. These columns are copied at scan
-- time (operator/scan_process.php) and never depend on the graduate row.
--
-- Apply once:  mysql -u root -p gradscan < migrations/2026-09-30_scan_log_school_year.sql

ALTER TABLE `scan_log`
  ADD COLUMN `school_id` int(11) DEFAULT NULL AFTER `operator_id`,
  ADD COLUMN `graduation_year` int(11) DEFAULT NULL AFTER `school_id`,
  ADD KEY `school_year_scanned` (`school_id`, `graduation_year`, `scanned_at`),
  ADD CONSTRAINT `scan_log_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `school` (`school_id`) ON DELETE SET NULL;

-- Backfill existing scans from their graduates (scans whose graduate was
-- already deleted cannot be recovered and stay NULL)
UPDATE `scan_log` s
  JOIN `qr_code` q ON q.qr_id = s.qr_id
  JOIN `graduate` g ON g.graduate_id = q.graduate_id
SET s.school_id = g.school_id,
    s.graduation_year = g.graduation_year
WHERE s.school_id IS NULL;
