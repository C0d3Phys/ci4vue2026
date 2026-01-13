<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        /**
         * 1) TENANT (Empresa)
         */
        $db->table('tenants')->insert([
            'name'       => 'Empresa Demo',
            'currency'   => 'USD',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $tenantId = (int) $db->insertID();

        /**
         * 2) SUCURSAL PRINCIPAL
         */
        $db->table('branches')->insert([
            'tenant_id'  => $tenantId,
            'name'       => 'Sucursal Principal',
            'code'       => 'MAIN',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $branchId = (int) $db->insertID();

        /**
         * 3) ROL SUPERADMIN
         */
        $db->table('roles')->insert([
            'tenant_id'  => $tenantId,
            'name'       => 'SuperAdmin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleId = (int) $db->insertID();

        /**
         * 4) USUARIO SUPERADMIN
         */
        $password = 'admin123'; // 👉 cambia luego en producción
        $hash     = password_hash($password, PASSWORD_DEFAULT);

        $db->table('users')->insert([
            'tenant_id'         => $tenantId,
            'default_branch_id' => $branchId,
            'name'              => 'Super Admin',
            'email'             => 'admin@demo.local',
            'password_hash'     => $hash,
            'status'            => 'active',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $userId = (int) $db->insertID();

        /**
         * 5) USER ↔ ROLE (SuperAdmin)
         */
        $db->table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        /**
         * 6) USER ↔ BRANCH (acceso a sucursal principal)
         */
        $db->table('user_branches')->insert([
            'user_id'   => $userId,
            'branch_id' => $branchId,
        ]);

        /**
         * 7) (Opcional) AUDIT LOG inicial
         */
        $db->table('audit_log')->insert([
            'tenant_id'  => $tenantId,
            'branch_id'  => $branchId,
            'user_id'    => $userId,
            'action'     => 'seed.core',
            'entity'     => 'system',
            'entity_id'  => null,
            'method'     => 'CLI',
            'path'       => 'db:seed CoreSeeder',
            'created_at' => $now,
        ]);

        echo "✔ CoreSeeder ejecutado correctamente\n";
        echo "Tenant ID: {$tenantId}\n";
        echo "Sucursal ID: {$branchId}\n";
        echo "Usuario SuperAdmin: admin@demo.local\n";
        echo "Password: {$password}\n";
    }
}
