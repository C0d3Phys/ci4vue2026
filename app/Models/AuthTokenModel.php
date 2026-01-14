<?php

// ============================================
// app/Models/AuthTokenModel.php
// ============================================

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AuthTokenModel extends Model
{
    protected $table = 'auth_tokens';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'user_id',
        'token',
        'expires_at',
        'revoked_at',
        'ip',
        'user_agent',
        'last_used_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = null;

    // Validation
    protected $validationRules = [
        'user_id' => 'required|integer',
        'token' => 'required|string|max_length[255]',
        'expires_at' => 'required|valid_date',
    ];

    protected $validationMessages = [];
    protected $skipValidation = false;


    
// -------------------------
// Custom Methods
// -------------------------

    /**
     * Buscar token válido por JTI
     */
    public function findValidByJti(string $jti): ?array
    {
        $token = $this->where('token', $jti)
            ->where('revoked_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();

        // Touch last_used_at
        if ($token) {
            $this->touchToken((int) $token['id']);
        }

        return $token;
    }

    /**
     * Crear nuevo token de sesión
     */
    public function createToken(int $userId, string $jti, string $expiresAt, ?string $ip = null, ?string $userAgent = null): bool
    {
        return $this->insert([
            'user_id' => $userId,
            'token' => $jti,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'last_used_at' => date('Y-m-d H:i:s'),
        ]) !== false;
    }

    /**
     * Revocar token por JTI
     */
    public function revokeByJti(string $jti): bool
    {
        return $this->where('token', $jti)
            ->where('revoked_at IS NULL')
            ->set(['revoked_at' => date('Y-m-d H:i:s')])
            ->update() !== false;
    }

    /**
     * Revocar todos los tokens de un usuario
     */
    public function revokeAllByUser(int $userId): bool
    {
        return $this->where('user_id', $userId)
            ->where('revoked_at IS NULL')
            ->set(['revoked_at' => date('Y-m-d H:i:s')])
            ->update() !== false;
    }

    /**
     * Limpiar tokens expirados
     */
    public function cleanupExpired(int $userId): bool
    {
        return $this->where('user_id', $userId)
            ->where('expires_at <', date('Y-m-d H:i:s'))
            ->delete() !== false;
    }

    /**
     * Obtener sesiones activas de un usuario
     */
    public function getActiveSessions(int $userId, int $limit = 10): array
    {
        return $this->select('id, token, expires_at, last_used_at, ip, user_agent, created_at')
            ->where('user_id', $userId)
            ->where('revoked_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orderBy('last_used_at', 'DESC')
            ->findAll($limit);
    }

    /**
     * Limitar sesiones concurrentes (mantener solo las N más recientes)
     */
    public function enforceSessionLimit(int $userId, int $maxSessions = 5): bool
    {
        $activeSessions = $this->where('user_id', $userId)
            ->where('revoked_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orderBy('last_used_at', 'DESC')
            ->findAll();

        if (count($activeSessions) >= $maxSessions) {
            $sessionsToRevoke = array_slice($activeSessions, $maxSessions - 1);
            $idsToRevoke = array_column($sessionsToRevoke, 'id');

            if (!empty($idsToRevoke)) {
                return $this->whereIn('id', $idsToRevoke)
                    ->set(['revoked_at' => date('Y-m-d H:i:s')])
                    ->update() !== false;
            }
        }

        return true;
    }

    /**
     * Actualizar last_used_at
     */
    public function touchToken(int $tokenId): bool
    {
        return $this->update($tokenId, [
            'last_used_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Estadísticas de tokens
     */
    public function getTokenStats(int $userId): array
    {
        $db = $this->db;

        $total = $this->where('user_id', $userId)->countAllResults();

        $active = $this->where('user_id', $userId)
            ->where('revoked_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->countAllResults();

        $revoked = $this->where('user_id', $userId)
            ->where('revoked_at IS NOT NULL')
            ->countAllResults();

        $expired = $this->where('user_id', $userId)
            ->where('expires_at <=', date('Y-m-d H:i:s'))
            ->where('revoked_at IS NULL')
            ->countAllResults();

        return [
            'total' => $total,
            'active' => $active,
            'revoked' => $revoked,
            'expired' => $expired,
        ];
    }


    /**
     * Limpia tokens expirados o revocados antiguos
     * Retorna cantidad de registros eliminados
     */
    public function cleanupOldTokens(int $daysOld = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $this->groupStart()
            ->where('expires_at <', $cutoff)
            ->orWhere('revoked_at <', $cutoff)
            ->groupEnd()
            ->delete();

        return $this->db->affectedRows();
    }
}
