-- Add submission_status columns to track whether each form has been submitted or saved as draft
-- This ensures only submitted forms appear in verifier queues across ALL form types

-- ============================================================================
-- 1. REQUISITION FORM: Add submission_status to requisition_item
-- ============================================================================
ALTER TABLE `requisition_item` 
ADD COLUMN `submission_status` ENUM('draft', 'submitted') DEFAULT 'draft' AFTER `urgent_note`;

-- Update existing requisition records to 'submitted' since they were previously accepted
UPDATE `requisition_item` 
SET `submission_status` = 'submitted' 
WHERE `request_id` IS NOT NULL;

-- Add index for requisition_item submission_status
CREATE INDEX idx_requisition_submission_status ON `requisition_item` (`submission_status`);

-- 2. CANVASS FORM: Add canvass_submission_status to requisition_canvass_detail
-- ============================================================================
ALTER TABLE `requisition_canvass_detail`
ADD COLUMN `canvass_submission_status` ENUM('draft', 'submitted') DEFAULT 'draft' AFTER `sort_order`;

-- Backfill from legacy canvass_verification_approval value, when present
UPDATE `requisition_canvass_detail` rcd
INNER JOIN `canvass_verification_approval` cva ON cva.request_id = rcd.request_id
SET rcd.`canvass_submission_status` = CASE
    WHEN LOWER(TRIM(COALESCE(cva.`canvas_submission_status`, 'draft'))) = 'submitted' THEN 'submitted'
    ELSE COALESCE(rcd.`canvass_submission_status`, 'draft')
END;

-- Add index for requisition_canvass_detail canvass_submission_status
CREATE INDEX idx_canvass_detail_submission_status ON `requisition_canvass_detail` (`canvass_submission_status`);

-- ============================================================================
-- 3. PURCHASE REQUISITION FORM: Add pr_submission_status to purchase_requisition_approval
-- ============================================================================
ALTER TABLE `purchase_requisition_approval` 
ADD COLUMN `pr_submission_status` ENUM('draft', 'submitted') DEFAULT 'draft' AFTER `pr_pres_at`;

-- Update existing PR records to 'submitted' if they have been reviewed or accepted
UPDATE `purchase_requisition_approval` 
SET `pr_submission_status` = 'submitted' 
WHERE `pr_inv_status` = 'accept' 
   OR `pr_pres_status` = 'accept' 
   OR `pr_inv_status` = 'reject' 
   OR `pr_pres_status` = 'reject';

-- Add index for purchase_requisition_approval submission_status
CREATE INDEX idx_pr_submission_status ON `purchase_requisition_approval` (`pr_submission_status`);
