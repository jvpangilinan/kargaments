<?php
// includes/audit_log.php
// Central helper for writing to Audit_Log.
// Usage:
//   require_once __DIR__ . '/../includes/audit_log.php';
//   log_action($pdo, $_SESSION['user_id'], 'Created product', 'Product', $newProductId);
//
// A failure to write the log is recorded in the PHP error log but never breaks the
// action the user just completed.

if (!function_exists('log_action')) {
    function log_action(PDO $pdo, int $userId, string $action, string $targetTable, int $targetId): void
    {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO Audit_Log (UserID, Action, TargetTable, TargetID)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$userId, mb_substr($action, 0, 255), $targetTable, $targetId]);
        } catch (PDOException $e) {
            error_log('Audit log write failed: ' . $e->getMessage());
        }
    }
}
