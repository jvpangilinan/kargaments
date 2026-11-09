<?php
// suppliers/review_submissions.php — Manager/Admin review supplier-submitted
// availability and delivery updates. Submissions stay Pending until approved or rejected.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

// Handle Approve / Reject (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $decision     = (string)($_POST['decision'] ?? '');
    $reviewNotes  = trim((string)($_POST['review_notes'] ?? ''));

    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        set_flash('error', 'Invalid decision.');
    } elseif (mb_strlen($reviewNotes) > 500) {
        set_flash('error', 'Review notes are too long (500 characters maximum).');
    } else {
        // Only a Pending submission can be decided; the WHERE clause also stops two reviewers
        // from deciding the same one.
        $stmt = $pdo->prepare(
            "UPDATE Supplier_Submission
             SET Status = ?, ReviewedBy = ?, ReviewedAt = NOW(), ReviewNotes = ?
             WHERE SubmissionID = ? AND Status = 'Pending'"
        );
        $stmt->execute([$decision, (int)$_SESSION['user_id'], $reviewNotes !== '' ? $reviewNotes : null, $submissionId]);

        if ($stmt->rowCount() === 1) {
            log_action($pdo, (int)$_SESSION['user_id'], "$decision supplier submission #$submissionId", 'Supplier_Submission', $submissionId);
            set_flash('success', $decision === 'Approved' ? "Approved. The supplier can see it now." : "Marked as not approved. The supplier can see your note.");
        } else {
            set_flash('error', 'That submission was not found or has already been reviewed.');
        }
    }
    header('Location: ' . url('suppliers/review_submissions.php'));
    exit;
}

$validStatuses = ['Pending', 'Approved', 'Rejected'];
$status = $_GET['status'] ?? 'Pending';
if ($status !== '' && !in_array($status, $validStatuses, true)) {
    $status = 'Pending';
}

$sql = "SELECT ss.*, s.Name AS SupplierName, p.Name AS ProductName, r.FullName AS ReviewerName
        FROM Supplier_Submission ss
        JOIN Supplier s ON ss.SupplierID = s.SupplierID
        JOIN Product p ON ss.ProductID = p.ProductID
        LEFT JOIN User r ON ss.ReviewedBy = r.UserID";
$params = [];
if ($status !== '') {
    $sql .= " WHERE ss.Status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY ss.SubmittedAt DESC, ss.SubmissionID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

$pageTitle = 'Supplier Submissions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Supplier Submissions</h1>
</div>

<div class="report-tabs">
    <a href="<?= e(url('suppliers/review_submissions.php?status=Pending')) ?>" class="<?= $status === 'Pending' ? 'active' : '' ?>">Pending</a>
    <a href="<?= e(url('suppliers/review_submissions.php?status=Approved')) ?>" class="<?= $status === 'Approved' ? 'active' : '' ?>">Approved</a>
    <a href="<?= e(url('suppliers/review_submissions.php?status=Rejected')) ?>" class="<?= $status === 'Rejected' ? 'active' : '' ?>">Rejected</a>
    <a href="<?= e(url('suppliers/review_submissions.php?status=')) ?>" class="<?= $status === '' ? 'active' : '' ?>">All</a>
</div>

<?php if (count($submissions) === 0): ?>
    <div class="empty-state">No submissions in this category.</div>
<?php else: ?>
    <?php foreach ($submissions as $s):
        $badgeClass = match ($s['Status']) {
            'Approved' => 'badge-received',
            'Rejected' => 'badge-cancelled',
            default    => 'badge-pending',
        };
    ?>
        <div class="card" style="text-align:left; margin-bottom:16px;">
            <div class="page-header" style="margin-bottom:8px;">
                <h3 style="margin:0;"><?= e($s['ProductName']) ?> — <?= e($s['SupplierName']) ?></h3>
                <span class="badge <?= $badgeClass ?>"><?= e(km_label($s['Status'], 'submission')) ?></span>
            </div>
            <p>
                <strong>Offered:</strong> <?= (int)$s['AvailableQuantity'] ?> units @ ₱<?= number_format((float)$s['UnitPrice'], 2) ?>
                &nbsp;|&nbsp; <strong>Delivery:</strong> <?= $s['DeliveryDate'] ? e(date('M j, Y', strtotime($s['DeliveryDate']))) : 'Not specified' ?>
                &nbsp;|&nbsp; <strong>Submitted:</strong> <?= e(date('M j, Y g:i A', strtotime($s['SubmittedAt']))) ?>
            </p>
            <?php if ($s['DeliveryNotes']): ?>
                <p><strong>Notes from supplier:</strong> <?= e($s['DeliveryNotes']) ?></p>
            <?php endif; ?>

            <?php if ($s['Status'] === 'Pending'): ?>
                <form method="POST" action="<?= e(url('suppliers/review_submissions.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submission_id" value="<?= (int)$s['SubmissionID'] ?>">
                    <div class="form-group">
                        <label for="notes_<?= (int)$s['SubmissionID'] ?>">Note to the supplier (optional)</label>
                        <input type="text" id="notes_<?= (int)$s['SubmissionID'] ?>" name="review_notes" maxlength="500"
                               placeholder="e.g. Good price, thank you">
                    </div>
                    <div class="form-actions">
                        <button class="btn" type="submit" name="decision" value="Approved" data-km="approve-<?= (int)$s['SubmissionID'] ?>"
                                data-confirm-title="Approve this offer?" data-confirm="<?= e($s['SupplierName']) ?> will see it was approved. Nothing is ordered yet." data-confirm-yes="Yes, approve">Approve</button>
                        <button class="btn btn-danger" type="submit" name="decision" value="Rejected" data-km="reject-<?= (int)$s['SubmissionID'] ?>"
                                data-confirm-title="Say no to this offer?" data-confirm="<?= e($s['SupplierName']) ?> will see it was not approved, with your note." data-confirm-yes="Yes, not approved">Not approve</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-muted">
                    Reviewed by <?= e($s['ReviewerName'] ?? '—') ?>
                    on <?= $s['ReviewedAt'] ? e(date('M j, Y g:i A', strtotime($s['ReviewedAt']))) : '—' ?>
                    <?php if ($s['ReviewNotes']): ?> — "<?= e($s['ReviewNotes']) ?>"<?php endif; ?>
                </p>
                <?php if ($s['Status'] === 'Approved'): ?>
                    <a class="btn btn-sm btn-secondary" href="<?= e(url('purchase_orders/create_order.php?product_id=' . (int)$s['ProductID'])) ?>">Order this item</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
