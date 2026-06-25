-- Add selection_source to requisition_line_awards (GSD suggested supplier source: preferred vs canvassed).
ALTER TABLE `requisition_line_awards`
  ADD COLUMN IF NOT EXISTS `selection_source` ENUM('preferred','canvassed') NULL DEFAULT NULL
  AFTER `supplier_id`;
