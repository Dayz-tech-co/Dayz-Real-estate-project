ALTER TABLE `agents`
  ADD COLUMN `cac_number` VARCHAR(120) NULL AFTER `business_address`,
  ADD COLUMN `years_of_experience` INT NULL AFTER `cac_number`;
