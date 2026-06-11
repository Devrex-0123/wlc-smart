-- Align empty office type values with cwirms.offices.type enum:
-- enum('Academics','Administrative','Executive')

UPDATE `offices`
SET `type` = 'Administrative'
WHERE (`type` IS NULL OR TRIM(`type`) = '')
  AND UPPER(`office_name`) LIKE '%OFFICE%';

UPDATE `offices`
SET `type` = 'Executive'
WHERE (`type` IS NULL OR TRIM(`type`) = '')
  AND UPPER(`office_name`) LIKE '%PRESIDENT%';

UPDATE `offices`
SET `type` = 'Academics'
WHERE `type` IS NULL OR TRIM(`type`) = '';
