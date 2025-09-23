-- Add evidence fields to tickets table
ALTER TABLE tickets 
ADD COLUMN evidence_file VARCHAR(255) NULL AFTER payment_method,
ADD COLUMN evidence_uploaded_at TIMESTAMP NULL,
ADD COLUMN evidence_uploaded_by INT NULL,
ADD FOREIGN KEY (evidence_uploaded_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create index for evidence queries
CREATE INDEX idx_tickets_evidence ON tickets(evidence_file);