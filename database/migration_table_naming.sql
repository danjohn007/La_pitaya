-- Migration to change table number from INT to VARCHAR for letter+number naming
-- Date: 2024-12-25
-- Description: Allow tables to be named with letter+number combinations like A1, B2, C3

USE ejercito_restaurant;

-- Change table number field from INT to VARCHAR to support letter+number combinations
ALTER TABLE tables 
MODIFY COLUMN number VARCHAR(10) UNIQUE NOT NULL;

-- Note: This will preserve existing numeric table numbers
-- New tables can now be created with letter+number combinations