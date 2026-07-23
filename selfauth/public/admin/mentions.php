<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Session;
use Selfauth\Support;
use Selfauth\Webmention;

Session::requireAuth();

$pdo = $GLOBALS['selfauth_pdo'];
$webmention = new Webmention($pdo, SELFAUTH_USER_URL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);
    $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if ($action === 'approve') {
        $webmention->setStatus($id, 'verified');
        admin_set_flash('ok', 'Marked as verified.');
    } elseif ($action === 'spam') {
        $webmention->setStatus($id, 'spam');
        admin_set_flash('ok', 'Marked as spam.');
    } elseif ($action === 'delete') {
        $webmention->delete($id);
        admin_set_flash('ok', 'Deleted.');
    } elseif ($action === 'recheck') {
        $ok = $webmention->verify($id, 8);
        admin_set_flash($ok ? 'ok' : 'error', $ok ? 'Re-verified successfully.' : 'Could not verify a link to the target was found.');
    }
    header('Location: mentions.php?status=' . urlencode((string) filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW)));
    exit;
}

$status = filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW) ?: 'all';
$counts = $webmention->counts();
$entries = $status === 'all' ? $webmention->all() : $webmention->byStatus($status);

admin_header('Webmentions', 'mentions.php');
?>
<h1>Webmentions</h1>
<p class="muted">
    Receiver endpoint: <code><?php echo Support::e(rtrim(SELFAUTH_APP_URL, '/')); ?>/webmention.php</code> &middot;
    Public read API: <code>?target=https://yoursite.com/post</code>
</p>

<div class="stats">
    <div class="stat"><div class="n"><?php echo $counts['verified']; ?></div><div class="l">Verified</div></div>
    <div class="stat"><div class="n"><?php echo $counts['pending']; ?></div><div class="l">Pending</div></div>
    <div class="stat"><div class="n"><?php echo $counts['failed']; ?></div><div class="l">Failed</div></div>
    <div class="stat"><div class="n"><?php echo $counts['spam']; ?></div><div class="l">Spam</div></div>
</div>

<?php admin_render_flash(); ?>

<div class="card">
    <div style="display:flex; gap:10px; margin-bottom:12px;">
        <?php foreach (['all' => 'All', 'verified' => 'Verified', 'pending' => 'Pending', 'failed' => 'Failed', 'spam' => 'Spam'] as $key => $label) : ?>
            <a href="?status=<?php echo $key; ?>" style="<?php echo $status === $key ? 'font-weight:700;' : ''; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($entries)) : ?>
        <div class="empty">No webmentions here.</div>
    <?php else : ?>
    <table>
        <tr><th>Source</th><th>Target</th><th>Status</th><th>Author</th><th>Received</th><th></th></tr>
        <?php foreach ($entries as $m) : ?>
        <tr>
            <td style="max-width:220px; overflow-wrap:anywhere;"><a href="<?php echo Support::e($m['source']); ?>" target="_blank" rel="noopener"><?php echo Support::e($m['source']); ?></a></td>
            <td style="max-width:200px; overflow-wrap:anywhere;"><?php echo Support::e($m['target']); ?></td>
            <td><span class="badge <?php echo $m['status'] === 'verified' ? 'ok' : ($m['status'] === 'pending' ? 'pending' : ($m['status'] === 'spam' ? 'spam' : 'bad')); ?>"><?php echo Support::e($m['status']); ?></span></td>
            <td><?php echo Support::e($m['author_name']); ?></td>
            <td class="muted"><?php echo Support::e($m['created_at']); ?></td>
            <td style="white-space:nowrap;">
                <form class="inline" method="POST">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                    <button name="action" value="recheck" class="secondary" style="padding:4px 8px;font-size:.8em;">Re-check</button>
                    <?php if ($m['status'] !== 'verified') : ?><button name="action" value="approve" style="padding:4px 8px;font-size:.8em;">Approve</button><?php endif; ?>
                    <?php if ($m['status'] !== 'spam') : ?><button name="action" value="spam" class="danger" style="padding:4px 8px;font-size:.8em;">Spam</button><?php endif; ?>
                    <button name="action" value="delete" class="danger" style="padding:4px 8px;font-size:.8em;" onclick="return confirm('Delete this mention?');">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
