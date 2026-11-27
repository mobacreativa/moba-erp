-- Correo electrónico de los usuarios para avisos de asignación. Aditiva e idempotente.
ALTER TABLE erp_users ADD COLUMN IF NOT EXISTS email VARCHAR(190) NOT NULL DEFAULT '' AFTER name;
