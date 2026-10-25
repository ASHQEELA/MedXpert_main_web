-- Adds a "verified" flag for doctors.
-- Run once in phpMyAdmin (SQL tab) on your existing database.
-- New installs already get this column from database.sql.
ALTER TABLE doctors
    ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
