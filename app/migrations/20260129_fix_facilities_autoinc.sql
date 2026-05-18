-- Migration: Set AUTO_INCREMENT on facilities.facility_id
-- Run this once in your database (phpMyAdmin or mysql CLI).

ALTER TABLE `facilities`
  MODIFY `facility_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- Notes:
-- This fixes the "Duplicate entry '0' for key 'PRIMARY'" error when inserting new facilities.
-- Make sure you have a recent backup before running migrations.