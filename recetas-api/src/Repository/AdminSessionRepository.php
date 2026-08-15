<?php

declare(strict_types=1);

namespace App\Repository;

use App\Auth\AdminIdentity;
use PDO;

final class AdminSessionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function storeState(string $hash, int $expiresAt): void
    {
        $this->cleanup(time());
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_auth_states (state_hash, expires_at) VALUES (:hash, :expires_at)'
        );
        $statement->execute(['hash' => $hash, 'expires_at' => $expiresAt]);
    }

    public function consumeState(string $hash, int $now): bool
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'SELECT expires_at FROM admin_auth_states WHERE state_hash = :hash'
            );
            $statement->execute(['hash' => $hash]);
            $expiresAt = $statement->fetchColumn();

            $delete = $this->pdo->prepare('DELETE FROM admin_auth_states WHERE state_hash = :hash');
            $delete->execute(['hash' => $hash]);
            $this->pdo->commit();

            return $expiresAt !== false && (int) $expiresAt >= $now;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function createSession(
        string $hash,
        AdminIdentity $identity,
        int $createdAt,
        int $expiresAt
    ): void {
        $this->cleanup($createdAt);
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_sessions (
                id_hash, google_subject, email, nombre, avatar_url,
                created_at, expires_at, last_seen_at
            ) VALUES (
                :id_hash, :google_subject, :email, :nombre, :avatar_url,
                :created_at, :expires_at, :last_seen_at
            )'
        );
        $statement->execute([
            'id_hash' => $hash,
            'google_subject' => $identity->id,
            'email' => $identity->email,
            'nombre' => $identity->nombre,
            'avatar_url' => $identity->avatarUrl,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
            'last_seen_at' => $createdAt,
        ]);
    }

    public function findValidSession(string $hash, int $now): ?AdminIdentity
    {
        $statement = $this->pdo->prepare(
            'SELECT google_subject, email, nombre, avatar_url
             FROM admin_sessions
             WHERE id_hash = :id_hash AND expires_at > :now'
        );
        $statement->execute(['id_hash' => $hash, 'now' => $now]);
        $session = $statement->fetch();
        if ($session === false) {
            return null;
        }

        return new AdminIdentity(
            (string) $session['google_subject'],
            (string) $session['email'],
            $session['nombre'] !== null ? (string) $session['nombre'] : null,
            $session['avatar_url'] !== null ? (string) $session['avatar_url'] : null
        );
    }

    public function deleteSession(string $hash): void
    {
        $statement = $this->pdo->prepare('DELETE FROM admin_sessions WHERE id_hash = :id_hash');
        $statement->execute(['id_hash' => $hash]);
    }

    private function cleanup(int $now): void
    {
        $sessions = $this->pdo->prepare('DELETE FROM admin_sessions WHERE expires_at <= :now');
        $sessions->execute(['now' => $now]);
        $states = $this->pdo->prepare('DELETE FROM admin_auth_states WHERE expires_at <= :now');
        $states->execute(['now' => $now]);
    }
}
