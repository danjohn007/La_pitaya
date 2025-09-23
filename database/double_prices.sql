-- Script to double all dish prices in the database
-- Run this script to update all active dish prices by multiplying them by 2

-- Show current prices before update
SELECT 'PRICES BEFORE UPDATE:' as action;
SELECT id, name, price as current_price, (price * 2) as new_price 
FROM dishes 
WHERE active = 1 
ORDER BY category, name;

-- Update all active dish prices
UPDATE dishes 
SET price = price * 2 
WHERE active = 1;

-- Show affected rows count
SELECT ROW_COUNT() as rows_updated;

-- Show updated prices
SELECT 'PRICES AFTER UPDATE:' as action;
SELECT id, name, price as updated_price, category
FROM dishes 
WHERE active = 1 
ORDER BY category, name;

-- Verification query
SELECT 'VERIFICATION:' as action, 
       COUNT(*) as total_dishes, 
       MIN(price) as min_price, 
       MAX(price) as max_price, 
       AVG(price) as avg_price
FROM dishes 
WHERE active = 1;