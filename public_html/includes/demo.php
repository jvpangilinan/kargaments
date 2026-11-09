<?php
// includes/demo.php — Demo mode (presenting) helpers.
// Only usable when DEMO_MODE is true in config/config.php AND an Admin started the demo.
//
// km_demo_reset() puts every table back to the demo data in includes/demo_seed.sql,
// with dates relative to "now". It keeps:
//   - the password of every account that also exists in the demo data, so a changed
//     admin password is not reset back to admin123;
//   - every non-demo Admin/Manager/Employee account you created yourself;
//   - each kept account's Guided mode setting.

const KM_DEMO_USERS = [
    'admin', 'manager1', 'employee1', 'employee2',
    'supplier_buildmax', 'supplier_voltpro', 'supplier_pacific',
];

if (!function_exists('km_demo_active')) {
    function km_demo_active(): bool
    {
        return DEMO_MODE && !empty($_SESSION['km_demo']['by']);
    }
}

if (!function_exists('km_demo_reset')) {
    function km_demo_reset(PDO $pdo): void
    {
        $seedFile = __DIR__ . '/demo_seed.sql';
        $sql = is_file($seedFile) ? file_get_contents($seedFile) : false;
        if ($sql === false) {
            throw new RuntimeException('includes/demo_seed.sql is missing.');
        }

        // Remember what must survive the reset.
        $users = $pdo->query(
            "SELECT u.*, r.RoleName FROM User u JOIN Role r ON r.RoleID = u.RoleID"
        )->fetchAll();
        $prefs = [];
        try {
            foreach ($pdo->query("SELECT UserID, GuidedMode FROM User_Preference")->fetchAll() as $p) {
                $prefs[(int)$p['UserID']] = $p['GuidedMode'];
            }
        } catch (PDOException $e) { /* table missing: nothing to keep */ }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ([
                'User_Preference', 'Audit_Log', 'Supplier_Submission', 'Purchase_Order_Item',
                'Purchase_Order', 'Stock_Transaction', 'Product', 'User', 'Supplier', 'Category', 'Role',
            ] as $table) {
                try {
                    $pdo->exec("DELETE FROM `$table`");
                    $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
                } catch (PDOException $e) {
                    if ($table !== 'User_Preference') throw $e;
                }
            }
            foreach (preg_split('/;\s*\n--\s*@@\s*\n/', $sql) as $stmt) {
                $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
                $stmt = rtrim($stmt, "; \n\r\t");
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
            }

            $byName  = [];
            foreach ($pdo->query("SELECT UserID, Username FROM User")->fetchAll() as $u) {
                $byName[$u['Username']] = (int)$u['UserID'];
            }
            $roleIds = [];
            foreach ($pdo->query("SELECT RoleID, RoleName FROM Role")->fetchAll() as $r) {
                $roleIds[$r['RoleName']] = (int)$r['RoleID'];
            }

            $keepHash = $pdo->prepare("UPDATE User SET PasswordHash = ? WHERE UserID = ?");
            $addUser  = $pdo->prepare(
                "INSERT INTO User (Username, PasswordHash, FullName, RoleID, SupplierID, Status)
                 VALUES (?, ?, ?, ?, NULL, ?)"
            );
            $addPref  = null;
            try {
                $addPref = $pdo->prepare("INSERT INTO User_Preference (UserID, GuidedMode) VALUES (?, ?)");
            } catch (PDOException $e) { $addPref = null; }

            foreach ($users as $old) {
                $newId = null;
                if (isset($byName[$old['Username']])) {
                    $newId = $byName[$old['Username']];
                    $keepHash->execute([$old['PasswordHash'], $newId]);
                } elseif ($old['RoleName'] !== 'Supplier' && isset($roleIds[$old['RoleName']])) {
                    $addUser->execute([$old['Username'], $old['PasswordHash'], $old['FullName'], $roleIds[$old['RoleName']], $old['Status']]);
                    $newId = (int)$pdo->lastInsertId();
                }
                $oldId = (int)$old['UserID'];
                if ($newId && $addPref && array_key_exists($oldId, $prefs) && $prefs[$oldId] !== null) {
                    try { $addPref->execute([$newId, (int)$prefs[$oldId]]); } catch (PDOException $e) { /* ignore */ }
                }
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}

if (!function_exists('km_login_as')) {
    // Switch the session to another account (used only by the demo tour).
    function km_login_as(PDO $pdo, string $username): bool
    {
        $stmt = $pdo->prepare(
            "SELECT u.UserID, u.Username, u.FullName, u.SupplierID, u.Status, r.RoleName
             FROM User u JOIN Role r ON r.RoleID = u.RoleID WHERE u.Username = ?"
        );
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u || $u['Status'] !== 'active') {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']     = (int)$u['UserID'];
        $_SESSION['username']    = $u['Username'];
        $_SESSION['full_name']   = $u['FullName'];
        $_SESSION['role']        = $u['RoleName'];
        $_SESSION['supplier_id'] = $u['SupplierID'] !== null ? (int)$u['SupplierID'] : null;
        return true;
    }
}
