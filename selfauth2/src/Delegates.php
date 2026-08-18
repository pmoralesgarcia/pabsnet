<?php

namespace Selfauth;

/**
 * Delegates are additional identities the owner has explicitly granted
 * access to the *admin portal*. They are never able to authenticate as
 * "me" through the IndieAuth endpoint -- that identity assertion is only
 * ever the owner's (see OidcClient::resolveRole()). Delegates only exist
 * via OIDC login; there is no shared password for them.
 */
class Delegates
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function add(string $type, string $value, string $role, ?string $note = null): void
    {
        if (!in_array($type, ['email', 'subject'], true)) {
            throw new \InvalidArgumentException('Invalid identity type');
        }
        if (!in_array($role, ['manager', 'viewer'], true)) {
            throw new \InvalidArgumentException('Invalid role');
        }
        $value = $type === 'email' ? strtolower(trim($value)) : trim($value);

        $stmt = $this->pdo->prepare(
            'INSERT INTO admins (identity_type, identity_value, role, note, created_at)
             VALUES (:type, :value, :role, :note, :created_at)
             ON CONFLICT(identity_type, identity_value) DO UPDATE SET role = :role, note = :note'
        );
        $stmt->execute([
            'type' => $type,
            'value' => $value,
            'role' => $role,
            'note' => $note,
            'created_at' => gmdate('c'),
        ]);
    }

    public function remove(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM admins WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM admins ORDER BY created_at DESC');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Returns 'manager'|'viewer'|null for the given email/subject pair. */
    public function roleFor(?string $email, ?string $subject): ?string
    {
        if ($email) {
            $stmt = $this->pdo->prepare('SELECT role FROM admins WHERE identity_type = "email" AND identity_value = ?');
            $stmt->execute([strtolower($email)]);
            $role = $stmt->fetchColumn();
            if ($role !== false) {
                return $role;
            }
        }
        if ($subject) {
            $stmt = $this->pdo->prepare('SELECT role FROM admins WHERE identity_type = "subject" AND identity_value = ?');
            $stmt->execute([$subject]);
            $role = $stmt->fetchColumn();
            if ($role !== false) {
                return $role;
            }
        }
        return null;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }
}
