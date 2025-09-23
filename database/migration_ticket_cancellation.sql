-- Migration for ticket cancellation functionality
-- Add cancellation fields to tickets table

ALTER TABLE tickets 
ADD COLUMN status ENUM('active', 'cancelled') DEFAULT 'active' AFTER payment_method,
ADD COLUMN cancelled_at TIMESTAMP NULL AFTER status,
ADD COLUMN cancelled_by INT NULL AFTER cancelled_at,
ADD COLUMN cancellation_reason TEXT NULL AFTER cancelled_by,
ADD FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create audit table for ticket cancellations
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

-- Index for better performance on cancelled tickets
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_cancelled_by ON tickets(cancelled_by);
CREATE INDEX idx_ticket_cancellations_date ON ticket_cancellations(cancelled_at);
CREATE INDEX idx_ticket_cancellations_user ON ticket_cancellations(cancelled_by);