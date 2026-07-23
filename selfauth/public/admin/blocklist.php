<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Blocklist;
use Selfauth\Session;
use Selfauth\Support;

Session::requireAuth();

$pdo = $GLOBALS['selfauth_pdo'];
$blocklist = new Blocklist($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if ($action === 'add') {
        $type = filter_input(INPUT_POST, 'type', FILTER_UNSAFE_RAW);
        $pattern = trim((string) filter_input(INPUT_POST, 'pattern', FILTER_UNSAFE_RAW));
        $note = trim((string) filter_input(INPUT_POST, 'note', FILTER_UNSAFE_RAW));

        if (!in_array($type, ['client_id', 'redirect_uri', 'ip'], true) || $pattern === '') {
            admin_set_flash('error', 'Please choose a type and provide a pattern.');
        } else {
            $blocklist->add($type, $pattern, $note ?: null);
            admin_set_flash('ok', 'Blocklist entry added.');
        }
    } elseif ($action === 'remove') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $blocklist->remove($id);
        admin_set_flash('ok', 'Blocklist entry removed.');
    }
    header('Location: blocklist.php');
    exit;
}

$entries = $blocklist->all();

admin_header('Blocklist', 'blocklist.php');
?>
<h1>Blocklist</h1>
<p class="muted">
    Block a client application, a redirect destination, or a source IP address from using this endpoint.
    <code>client_id</code> / <code>redirect_uri</code> patterns match on hostname (use <code>*.example.com</code> for a wildcard).
    IP entries accept a single address or CIDR range (e.g. <code>203.0.113.0/24</code>).
</p>

<?php admin_render_flash(); ?>

<div class="card">
    <h2>Add entry</h2>
    <form method="POST">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="form-line">
            <label>Type</label>
            <select name="type">
                <option value="client_id">Client ID (hostname)</option>
                <option value="redirect_uri">Redirect URI (hostname)</option>
                <option value="ip">IP address / CIDR</option>
            </select>
        </div>
        <div class="form-line" style="margin-top:8px;">
            <label>Pattern</label>
            <input type="text" name="pattern" placeholder="example.com or *.example.com or 203.0.113.4" required>
        </div>
        <div class="form-line" style="margin-top:8px;">
            <label>Note (optional)</label>
            <input type="text" name="note" placeholder="Why is this blocked?">
        </div>
        <div class="form-line" style="margin-top:12px;">
            <input type="submit" value="Add to blocklist">
        </div>
    </form>
</div>

<div class="card">
    <h2>Current blocklist (<?php echo count($entries); ?>)</h2>
    <?php if (empty($entries)) : ?>
        <div class="empty">Nothing blocked yet.</div>
    <?php else : ?>
    <table>
        <tr><th>Type</th><th>Pattern</th><th>Note</th><th>Added</th><th></th></tr>
        <?php foreach ($entries as $e) : ?>
        <tr>
            <td><?php echo Support::e($e['type']); ?></td>
            <td><?php echo Support::e($e['pattern']); ?></td>
            <td class="muted"><?php echo Support::e($e['note']); ?></td>
            <td class="muted"><?php echo Support::e($e['created_at']); ?></td>
            <td>
                <form method="POST" onsubmit="return confirm('Remove this blocklist entry?');">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
                    <button type="submit" class="secondary" style="padding:4px 8px;font-size:.8em;">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
