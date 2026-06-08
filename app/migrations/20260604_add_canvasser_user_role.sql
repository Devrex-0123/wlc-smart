-- Add Canvasser to user.role enum (account management / canvasser workspace)
ALTER TABLE `user`
  MODIFY `role` enum(
    'Inventory Manager',
    'Dean',
    'Laboratory Manager',
    'Comptroller',
    'President',
    'Employee',
    'User',
    'GSD officer',
    'Canvasser'
  ) NOT NULL DEFAULT 'User';
