<?php
// includes/prefs.php — per-account settings stored in User_Preference.
// Guided mode: null = never asked (the mascot asks), true = on, false = off.
// If the User_Preference table is missing (old database), everything keeps working
// and guided mode simply counts as "off".

if (!function_exists('km_get_guided')) {
    function km_get_guided(PDO $pdo, int $userId): ?bool
    {
        try {
            $stmt = $pdo->prepare("SELECT GuidedMode FROM User_Preference WHERE UserID = ?");
            $stmt->execute([$userId]);
            $val = $stmt->fetchColumn();
            if ($val === false || $val === null) {
                return null;
            }
            return (int)$val === 1;
        } catch (PDOException $e) {
            error_log('User_Preference read failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('km_set_guided')) {
    function km_set_guided(PDO $pdo, int $userId, bool $on): bool
    {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO User_Preference (UserID, GuidedMode) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE GuidedMode = VALUES(GuidedMode)"
            );
            $stmt->execute([$userId, $on ? 1 : 0]);
            return true;
        } catch (PDOException $e) {
            error_log('User_Preference write failed: ' . $e->getMessage());
            return false;
        }
    }
}
