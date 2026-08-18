<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Delegates;
use Selfauth\Session;
use Selfauth\Support;

Session::requireAuth();
admin_require_role('owner');

$pdo = $GLOBALS['selfauth_pdo'];
$delegates = new Delegates($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    admin_require_role('owner');
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if ($action === 'add') {
        $type = filter_input(INPUT_POST, 'type', FILTER_UNSAFE_RAW);
        $value = trim((string) filter_input(INPUT_POST, 'value', FILTER_UNSAFE_RAW));
        $role = filter_input(INPUT_POST, 'role', FILTER_UNSAFE_RAW);
        $note = trim((string) filter_input(INPUT_POST, 'note', FILTER_UNSAFE_RAW));

        if (!in_array($type, ['email', 'subject'], true) || $value === '' || !in_array($role, ['manager', 'viewer'], true)) {
            admin_set_flash('error', 'Please provide a valid identity type, value, and role.');
        } else {
            $delegates->add($type, $value, $role, $note ?: null);
            admin_set_flash('ok', 'Delegate added: ' . $value);
        }
    } elseif ($action === 'remove') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $delegates->remove($id);
        admin_set_flash('ok', 'Delegate removed.');
    }
    header('Location: admins.php');
    exit;
}

$entries = $delegates->all();

admin_header('Admins', 'admins.php');
?>
<h1>Delegated admin access</h1>
<p class="muted">
    Grant someone else access to this admin portal via SSO, without sharing your password and without letting them
    authenticate as <strong><?php echo Support::e(SELFAUTH_USER_URL); ?></strong> to other websites — that IndieAuth
    "me" identity is always yours alone. <strong>Manager</strong> can act on sign-ins/blocklist/webmentions.
    <strong>Viewer</strong> can only look. Requires <a href="settings.php">admin SSO</a> to be enabled.
</p>

<?php admin_render_flash(); ?>

<div class="card">
    <h2>Add a delegate</h2>
    <form method="POST">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="form-line">
            <label>Identity type</label>
            <select name="type">
                <option value="email">Email (from the IdP's <code>email</code> claim)</option>
                <option value="subject">Subject (the IdP's stable <code>sub</code> claim, e.g. a Kanidm UUID)</option>
            </select>
        </div>
        <div class="form-line" style="margin-top:8px;">
            <label>Value</label>
            <input type="text" name="value" placeholder="teammate@example.com" required>
        </div>
        <div class="form-line" style="margin-top:8px;">
            <label>Role</label>
            <select name="role">
                <option value="manager">Manager — can act</option>
                <option value="viewer">Viewer — read-only</option>
            </select>
        </div>
        <div class="form-line" style="margin-top:8px;">
            <label>Note (optional)</label>
            <input type="text" name="note" placeholder="Why do they have access?">
        </div>
        <div class="form-line" style="margin-top:12px;"><input type="submit" value="Add delegate"></div>
    </form>
</div>

<div class="card">
    <h2>Current delegates (<?php echo count($entries); ?>)</h2>
    <?php if (empty($entries)) : ?>
        <div class="empty">No delegates yet — only you (the owner) have admin access.</div>
    <?php else : ?>
    <table>
        <tr><th>Type</th><th>Identity</th><th>Role</th><th>Note</th><th>Added</th><th></th></tr>
        <?php foreach ($entries as $d) : ?>
        <tr>
            <td data-label="Type"><?php echo Support::e($d['identity_type']); ?></td>
            <td data-label="Identity"><?php echo Support::e($d['identity_value']); ?></td>
            <td data-label="Role"><?php echo Support::e($d['role']); ?></td>
            <td data-label="Note" class="muted"><?php echo Support::e($d['note']); ?></td>
            <td data-label="Added" class="muted"><?php echo Support::e($d['created_at']); ?></td>
            <td>
                <form method="POST" onsubmit="return confirm('Remove this delegate\'s admin access?');">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
                    <button type="submit" class="secondary" style="padding:4px 8px;font-size:.8em;">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
