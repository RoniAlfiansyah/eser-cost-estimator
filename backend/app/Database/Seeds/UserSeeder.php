<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $seedPassword = (string) env('SEED_DEFAULT_PASSWORD', '');
        if (strlen($seedPassword) < 12) {
            throw new \RuntimeException('Set SEED_DEFAULT_PASSWORD with at least 12 characters before running UserSeeder.');
        }

        $users = [
            [
                'name'         => 'System Admin',
                'email'        => 'admin@projectcosting.test',
                'password'     => password_hash($seedPassword, PASSWORD_DEFAULT),
                'role'         => UserModel::ROLE_ADMIN,
                'is_active'    => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'Project Manager',
                'email'        => 'manager@projectcosting.test',
                'password'     => password_hash($seedPassword, PASSWORD_DEFAULT),
                'role'         => UserModel::ROLE_MANAGER,
                'is_active'    => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'Project Staff',
                'email'        => 'staff@projectcosting.test',
                'password'     => password_hash($seedPassword, PASSWORD_DEFAULT),
                'role'         => UserModel::ROLE_STAFF,
                'is_active'    => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('users')->truncate();
        $this->db->table('users')->insertBatch($users);
    }
}
