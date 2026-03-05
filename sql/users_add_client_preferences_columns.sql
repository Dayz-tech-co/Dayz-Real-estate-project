ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `investment_budget_range` VARCHAR(120) NULL AFTER `streetname`,
  ADD COLUMN IF NOT EXISTS `preferred_locations` TEXT NULL AFTER `investment_budget_range`,
  ADD COLUMN IF NOT EXISTS `property_type_interest` VARCHAR(180) NULL AFTER `preferred_locations`;
