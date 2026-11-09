<?php
// includes/guide_state.php — the real account numbers that Guided mode uses to pick the
// ONE next thing to highlight (see the GUIDE RULES section of assets/js/km.js).
// A few small COUNT queries; only run when Guided mode is on or the home page needs them.

if (!function_exists('km_guide_state')) {
    function km_guide_state(PDO $pdo, string $role, ?int $supplierId): array
    {
        $s = [];
        try {
            if ($role === 'Supplier') {
                if ($supplierId) {
                    $q = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE SupplierID = ? AND Quantity <= MinThreshold");
                    $q->execute([$supplierId]);
                    $s['myLow'] = (int)$q->fetchColumn();
                    $q = $pdo->prepare("SELECT COUNT(*) FROM Supplier_Submission WHERE SupplierID = ? AND Status = 'Pending'");
                    $q->execute([$supplierId]);
                    $s['myWaiting'] = (int)$q->fetchColumn();
                    $q = $pdo->prepare("SELECT COUNT(*) FROM Supplier_Submission WHERE SupplierID = ?");
                    $q->execute([$supplierId]);
                    $s['mySubs'] = (int)$q->fetchColumn();
                }
                return $s;
            }

            $s['low']      = (int)$pdo->query("SELECT COUNT(*) FROM Product WHERE Quantity <= MinThreshold")->fetchColumn();
            $s['products'] = (int)$pdo->query("SELECT COUNT(*) FROM Product")->fetchColumn();
            $s['salesToday'] = (int)$pdo->query(
                "SELECT COUNT(*) FROM Stock_Transaction WHERE TransactionType = 'Sale' AND TransactionDate >= CURDATE()"
            )->fetchColumn();

            if ($role === 'Admin' || $role === 'Manager') {
                $s['pendingPO']   = (int)$pdo->query("SELECT COUNT(*) FROM Purchase_Order WHERE Status = 'Pending'")->fetchColumn();
                $s['pendingSubs'] = (int)$pdo->query("SELECT COUNT(*) FROM Supplier_Submission WHERE Status = 'Pending'")->fetchColumn();
                // Low items that are NOT already on an order waiting for delivery.
                $s['lowNoOrder'] = (int)$pdo->query(
                    "SELECT COUNT(*) FROM Product p
                     WHERE p.Quantity <= p.MinThreshold
                       AND NOT EXISTS (
                           SELECT 1 FROM Purchase_Order_Item i
                           JOIN Purchase_Order o ON o.OrderID = i.OrderID
                           WHERE i.ProductID = p.ProductID AND o.Status = 'Pending')"
                )->fetchColumn();
                $first = $pdo->query(
                    "SELECT p.ProductID FROM Product p
                     WHERE p.Quantity <= p.MinThreshold
                       AND NOT EXISTS (
                           SELECT 1 FROM Purchase_Order_Item i
                           JOIN Purchase_Order o ON o.OrderID = i.OrderID
                           WHERE i.ProductID = p.ProductID AND o.Status = 'Pending')
                     ORDER BY (p.MinThreshold - p.Quantity) DESC, p.Name LIMIT 1"
                )->fetchColumn();
                $s['firstLowNoOrder'] = $first !== false ? (int)$first : 0;
                $po = $pdo->query("SELECT OrderID FROM Purchase_Order WHERE Status = 'Pending' ORDER BY OrderDate LIMIT 1")->fetchColumn();
                $s['firstPendingPO'] = $po !== false ? (int)$po : 0;
            }
            if ($role === 'Admin') {
                $last = $pdo->query(
                    "SELECT MAX(Timestamp) FROM Audit_Log WHERE TargetTable = 'Backup'"
                )->fetchColumn();
                $s['daysSinceBackup'] = $last ? (int)floor((time() - strtotime($last)) / 86400) : -1;
            }
        } catch (PDOException $e) {
            error_log('Guide state failed: ' . $e->getMessage());
        }
        return $s;
    }
}
