<?php
// app/Database/Migrations/2026-01-06-000001_CreateCoreModule.php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCoreModule extends Migration
{
    public function up()
    {
        /**
         * CORE MVP (multi-empresa + multi-sucursal + roles + auth tokens + audit light)
         *
         * Tablas:
         * - tenants
         * - branches
         * - users
         * - user_branches (allowlist)
         * - roles
         * - user_roles
         * - auth_tokens (para revocación/logout; token=jti o token random)
         * - audit_log (ligero)
         */

        // 1) tenants
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'currency'   => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'USD'],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->createTable('tenants', true);

        // 2) branches (sucursales)
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], // opcional: MAN, LEON, etc.
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'name']);
        $this->forge->addKey(['tenant_id', 'code'], false, true); // único por tenant si usas code
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('branches', true);

        // 3) users
        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'         => ['type' => 'BIGINT', 'unsigned' => true],
            'default_branch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 160],
            'password_hash'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active/blocked
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // email único por tenant (evita choque entre empresas)
        $this->forge->addKey(['tenant_id', 'email'], false, true);
        $this->forge->addKey(['tenant_id', 'default_branch_id']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('default_branch_id', 'branches', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('users', true);

        // 4) user_branches (allowlist: a qué sucursales puede acceder)
        $this->forge->addField([
            'user_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'branch_id' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['user_id', 'branch_id'], true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('user_branches', true);

        // 5) roles
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'name'], false, true); // nombre de rol único por tenant
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('roles', true);

        // 6) user_roles
        $this->forge->addField([
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'role_id' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['user_id', 'role_id'], true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('user_roles', true);

        // 7) auth_tokens (revocación/logout)
        // Nota: aquí puedes guardar:
        // - token random (varchar 128) si NO usas JWT todavía
        // - o jti (uuid/hex) si luego migras a JWT
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'token'      => ['type' => 'VARCHAR', 'constraint' => 128], // token o jti
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'token'], false, true); // token único por usuario
        $this->forge->addKey('token'); // lookup rápido por token
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('auth_tokens', true);

        // 8) audit_log (ligero, útil, barato)
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'branch_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 80], // ej: auth.login, users.create
            'entity'     => ['type' => 'VARCHAR', 'constraint' => 80], // ej: users
            'entity_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'method'     => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'path'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'branch_id', 'entity', 'entity_id']);
        $this->forge->addKey(['tenant_id', 'action']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('audit_log', true);
    }

    public function down()
    {
        $this->forge->dropTable('audit_log', true);
        $this->forge->dropTable('auth_tokens', true);
        $this->forge->dropTable('user_roles', true);
        $this->forge->dropTable('roles', true);
        $this->forge->dropTable('user_branches', true);
        $this->forge->dropTable('users', true);
        $this->forge->dropTable('branches', true);
        $this->forge->dropTable('tenants', true);
    }
}
