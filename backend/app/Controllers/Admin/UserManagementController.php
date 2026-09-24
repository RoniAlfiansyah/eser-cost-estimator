<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class UserManagementController extends BaseController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index(): string
    {
        return view('admin/users/index', [
            'title' => 'User Management',
            'users' => $this->userModel->orderBy('id', 'ASC')->findAll(),
        ]);
    }

    public function create(): string
    {
        return view('admin/users/form', [
            'title'       => 'Tambah User',
            'user'        => null,
            'roles'       => $this->roles(),
            'formAction'  => site_url('admin/users'),
            'submitLabel' => 'Simpan User',
        ]);
    }

    public function store()
    {
        $rules = $this->validationRules();

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->userModel->insert([
            'name'      => (string) $this->request->getPost('name'),
            'email'     => (string) $this->request->getPost('email'),
            'password'  => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role'      => (string) $this->request->getPost('role'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ]);

        $newUserId = $this->userModel->getInsertID();

        write_audit_log([
            'module'      => 'user_management',
            'action'      => 'create_user',
            'description' => 'Menambahkan user baru.',
            'target_type' => 'user',
            'target_id'   => $newUserId,
            'properties'  => [
                'name'      => (string) $this->request->getPost('name'),
                'email'     => (string) $this->request->getPost('email'),
                'role'      => (string) $this->request->getPost('role'),
                'is_active' => (bool) $this->request->getPost('is_active'),
            ],
        ]);

        return redirect()->to('/admin/users')->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(int $id): string
    {
        $user = $this->findUserOrFail($id);

        return view('admin/users/form', [
            'title'       => 'Edit User',
            'user'        => $user,
            'roles'       => $this->roles(),
            'formAction'  => site_url("admin/users/{$id}/update"),
            'submitLabel' => 'Update User',
        ]);
    }

    public function update(int $id)
    {
        $user = $this->findUserOrFail($id);
        $rules = $this->validationRules($id, false);

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name'      => (string) $this->request->getPost('name'),
            'email'     => (string) $this->request->getPost('email'),
            'role'      => (string) $this->request->getPost('role'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ((int) current_user('id') === (int) $user['id']) {
            $data['is_active'] = 1;
        }

        $this->userModel->update($id, $data);

        write_audit_log([
            'module'      => 'user_management',
            'action'      => 'update_user',
            'description' => 'Memperbarui data user.',
            'target_type' => 'user',
            'target_id'   => $id,
            'properties'  => [
                'before' => [
                    'name'      => $user['name'],
                    'email'     => $user['email'],
                    'role'      => $user['role'],
                    'is_active' => $user['is_active'],
                ],
                'after' => [
                    'name'      => $data['name'],
                    'email'     => $data['email'],
                    'role'      => $data['role'],
                    'is_active' => $data['is_active'],
                    'password_changed' => array_key_exists('password', $data),
                ],
            ],
        ]);

        return redirect()->to('/admin/users')->with('success', 'User berhasil diperbarui.');
    }

    public function toggleStatus(int $id)
    {
        $user = $this->findUserOrFail($id);

        if ((int) current_user('id') === (int) $user['id']) {
            return redirect()->to('/admin/users')->with('error', 'Akun yang sedang dipakai tidak bisa dinonaktifkan.');
        }

        $this->userModel->update($id, [
            'is_active' => $user['is_active'] ? 0 : 1,
        ]);

        write_audit_log([
            'module'      => 'user_management',
            'action'      => 'toggle_user_status',
            'description' => $user['is_active'] ? 'Menonaktifkan user.' : 'Mengaktifkan user.',
            'target_type' => 'user',
            'target_id'   => $id,
            'properties'  => [
                'email'      => $user['email'],
                'old_status' => $user['is_active'],
                'new_status' => ! $user['is_active'],
            ],
        ]);

        return redirect()->to('/admin/users')->with('success', 'Status user berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $user = $this->findUserOrFail($id);

        if ((int) current_user('id') === (int) $user['id']) {
            return redirect()->to('/admin/users')->with('error', 'Akun yang sedang dipakai tidak bisa dihapus.');
        }

        write_audit_log([
            'module'      => 'user_management',
            'action'      => 'delete_user',
            'description' => 'Menghapus user dari sistem.',
            'target_type' => 'user',
            'target_id'   => $id,
            'properties'  => [
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);

        $this->userModel->delete($id);

        return redirect()->to('/admin/users')->with('success', 'User berhasil dihapus.');
    }

    private function findUserOrFail(int $id): array
    {
        $user = $this->userModel->find($id);

        if ($user === null) {
            throw PageNotFoundException::forPageNotFound('User tidak ditemukan.');
        }

        return $user;
    }

    private function roles(): array
    {
        return [
            UserModel::ROLE_ADMIN   => 'Admin',
            UserModel::ROLE_MANAGER => 'Manager',
            UserModel::ROLE_STAFF   => 'Staff',
        ];
    }

    private function validationRules(?int $id = null, bool $requirePassword = true): array
    {
        $passwordRules = $requirePassword ? 'required|min_length[6]' : 'permit_empty|min_length[6]';
        $emailRules = 'required|valid_email|is_unique[users.email]';

        if ($id !== null) {
            $emailRules = "required|valid_email|is_unique[users.email,id,{$id}]";
        }

        return [
            'name'      => 'required|min_length[3]|max_length[100]',
            'email'     => $emailRules,
            'password'  => $passwordRules,
            'role'      => 'required|in_list[admin,manager,staff]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
    }
}
