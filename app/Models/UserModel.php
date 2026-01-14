<?php

// ============================================
// app/Models/UserModel.php
// ============================================

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'tenant_id',
        'default_branch_id',
        'name',
        'email',
        'password_hash',
        'status',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'tenant_id' => 'required|integer',
        'name'      => 'required|string|max_length[255]',
        'email'     => 'required|valid_email|max_length[255]',
        'status'    => 'in_list[active,inactive,blocked]',
    ];

    protected $validationMessages = [
        'email' => [
            'required'    => 'El email es requerido',
            'valid_email' => 'El email debe ser válido',
        ],
        'name' => [
            'required' => 'El nombre es requerido',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['hashPassword'];
    protected $beforeUpdate   = ['hashPassword'];

    // -------------------------
    // Custom Methods
    // -------------------------

    /**
     * Buscar usuario por email y tenant
     */
    public function findByEmailAndTenant(string $email, int $tenantId): ?array
    {
        return $this->where('email', strtolower(trim($email)))
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Buscar solo usuarios activos
     */
    public function findActive(int $userId): ?array
    {
        return $this->where('id', $userId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Obtener usuarios por tenant
     */
    public function getUsersByTenant(int $tenantId, int $limit = 100): array
    {
        return $this->where('tenant_id', $tenantId)
            ->orderBy('name', 'ASC')
            ->findAll($limit);
    }

    /**
     * Verificar si el usuario existe y está activo
     */
    public function isActive(int $userId): bool
    {
        $user = $this->find($userId);
        return $user && ($user['status'] ?? '') === 'active';
    }

    /**
     * Cambiar estado del usuario
     */
    public function changeStatus(int $userId, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive', 'blocked'])) {
            return false;
        }

        return $this->update($userId, ['status' => $status]);
    }

    // -------------------------
    // Callbacks
    // -------------------------

    protected function hashPassword(array $data): array
    {
        if (isset($data['data']['password']) && is_string($data['data']['password'])) {
            $data['data']['password_hash'] = password_hash(
                $data['data']['password'],
                PASSWORD_DEFAULT
            );
            unset($data['data']['password']);
        }

        return $data;
    }
}
