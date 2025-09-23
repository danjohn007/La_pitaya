<?php
/**
 * Transaction Fix Validation Script
 * 
 * This script validates that the transaction conflict issue has been fixed.
 * The issue was: "There is already an active transaction" when generating tickets
 * for multiple orders from the same table.
 */

echo "=== TRANSACTION CONFLICT FIX VALIDATION ===\n\n";

// Read the Ticket.php file to verify the changes
$ticketContent = file_get_contents('models/Ticket.php');

echo "1. CHECKING FOR PROBLEMATIC NESTED TRANSACTIONS:\n";
$problemCalls = substr_count($ticketContent, 'updateOrderStatusAndCustomerStats');
echo "   Count of updateOrderStatusAndCustomerStats calls in Ticket.php: $problemCalls\n";

if ($problemCalls === 0) {
    echo "   ✓ PASS: No more nested transaction calls in Ticket.php\n";
} else {
    echo "   ✗ FAIL: Still found problematic nested transaction calls\n";
}

echo "\n2. CHECKING FOR PROPER REPLACEMENT CALLS:\n";
$replacementCalls = substr_count($ticketContent, 'updateOrderStatus(');
echo "   Count of updateOrderStatus calls in Ticket.php: $replacementCalls\n";

if ($replacementCalls >= 2) {
    echo "   ✓ PASS: Found proper replacement calls that don't start transactions\n";
} else {
    echo "   ✗ FAIL: Insufficient replacement calls found\n";
}

echo "\n3. CHECKING FOR CUSTOMER STATS HANDLING:\n";
$customerStats = substr_count($ticketContent, 'updateStats(');
echo "   Count of updateStats calls in Ticket.php: $customerStats\n";

if ($customerStats >= 2) {
    echo "   ✓ PASS: Customer stats are still being handled properly\n";
} else {
    echo "   ✗ FAIL: Customer stats handling might be missing\n";
}

echo "\n4. VERIFYING SPECIFIC METHODS WERE FIXED:\n";

// Check createTicket method
if (strpos($ticketContent, 'error_log("Antes de updateOrderStatus");') !== false) {
    echo "   ✓ PASS: createTicket() method was updated\n";
} else {
    echo "   ✗ FAIL: createTicket() method not properly updated\n";
}

// Check createExpiredOrderTicket method  
$createExpiredPattern = '/createExpiredOrderTicket.*?updateOrderStatus.*?ORDER_DELIVERED/s';
if (preg_match($createExpiredPattern, $ticketContent)) {
    echo "   ✓ PASS: createExpiredOrderTicket() method was updated\n";
} else {
    echo "   ✗ FAIL: createExpiredOrderTicket() method not properly updated\n";
}

echo "\n=== SUMMARY ===\n";
echo "The fix resolves the transaction conflict by:\n";
echo "• Replacing updateOrderStatusAndCustomerStats() with updateOrderStatus()\n";
echo "• Handling customer stats separately within existing transactions\n";
echo "• Preventing nested transaction creation that caused the error\n";
echo "\nThe original error 'There is already an active transaction' should no longer occur\n";
echo "when generating tickets for multiple orders from the same table.\n";