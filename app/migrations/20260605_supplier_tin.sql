-- Optional Tax Identification Number for BIR withholding tax (Philippine suppliers).

ALTER TABLE `suppliers`
  ADD COLUMN `tin` VARCHAR(20) NULL DEFAULT NULL AFTER `postal_code`;
