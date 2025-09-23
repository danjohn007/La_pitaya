-- Update tickets table to fix database insertion issues
-- This ensures all required columns and constraints are properly set

USE ejercito_restaurant;

-- First, check current state and update payment methods
ALTER TABLE tickets 
MODIFY COLUMN payment_method ENUM('efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar') DEFAULT 'efectivo';

-- Add status column if it doesn't exist
ALTER TABLE tickets 
ADD COLUMN IF NOT EXISTS status ENUM('active', 'cancelled') DEFAULT 'active' AFTER payment_method;

-- Add evidence fields if they don't exist
ALTER TABLE tickets 
ADD COLUMN IF NOT EXISTS evidence_file VARCHAR(255) NULL AFTER status,
ADD COLUMN IF NOT EXISTS evidence_uploaded_at TIMESTAMP NULL AFTER evidence_file,
ADD COLUMN IF NOT EXISTS evidence_uploaded_by INT NULL AFTER evidence_uploaded_at;

-- Add cancellation fields if they don't exist
ALTER TABLE tickets 
ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL AFTER evidence_uploaded_by,
ADD COLUMN IF NOT EXISTS cancelled_by INT NULL AFTER cancelled_at,
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL AFTER cancelled_by;

-- Add foreign key constraints if they don't exist
-- Note: Using IF NOT EXISTS syntax may not work on older MySQL versions, so we'll use a different approach

-- Check if foreign key exists before adding
SET @fk_evidence_exists = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                          WHERE TABLE_SCHEMA = 'ejercito_restaurant' 
                          AND TABLE_NAME = 'tickets' 
                          AND CONSTRAINT_NAME = 'fk_tickets_evidence_uploaded_by');

SET @sql = IF(@fk_evidence_exists = 0, 
              'ALTER TABLE tickets ADD FOREIGN KEY fk_tickets_evidence_uploaded_by (evidence_uploaded_by) REFERENCES users(id) ON DELETE SET NULL', 
              'SELECT "Foreign key fk_tickets_evidence_uploaded_by already exists"');
              
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if cancelled_by foreign key exists before adding
SET @fk_cancelled_exists = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                           WHERE TABLE_SCHEMA = 'ejercito_restaurant' 
                           AND TABLE_NAME = 'tickets' 
                           AND CONSTRAINT_NAME = 'fk_tickets_cancelled_by');

SET @sql = IF(@fk_cancelled_exists = 0, 
              'ALTER TABLE tickets ADD FOREIGN KEY fk_tickets_cancelled_by (cancelled_by) REFERENCES users(id) ON DELETE SET NULL', 
              'SELECT "Foreign key fk_tickets_cancelled_by already exists"');
              
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create audit table for ticket cancellations if it doesn't exist
CREATE TABLE IF NOT EXISTS ticket_cancellations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    cancelled_by INT NOT NULL,
    reason TEXT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_evidence ON tickets(evidence_file);
CREATE INDEX IF NOT EXISTS idx_tickets_cancelled_by ON tickets(cancelled_by);
CREATE INDEX IF NOT EXISTS idx_ticket_cancellations_date ON ticket_cancellations(cancelled_at);
CREATE INDEX IF NOT EXISTS idx_ticket_cancellations_user ON ticket_cancellations(cancelled_by);

-- Show the updated table structure
DESCRIBE tickets;

-- Commit the changes
COMMIT;