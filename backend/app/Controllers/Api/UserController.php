<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class UserController extends AdminApiController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $builder = $this->userModel->orderBy('id', 'ASC');
        $keyword = trim((string) $this->request->getGet('q'));

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('name', $keyword)
                ->orLike('email', $keyword)
                ->orLike('role', $keyword)
                ->groupEnd();
        }

        $users = array_map(fn (array $user): array => $this->transformUser($user), $builder->findAll());

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $users,
        ]);
    }

    public function show(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $this->transformUser($this->findUserOrFail($id)),
        ]);
    }

    public function store()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = $this->validationRules();

        if (! $this->validateData($payload, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Data user tidak valid.',
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $this->userModel->insert([
            'name'      => (string) $payload['name'],
            'email'     => (string) $payload['email'],
            'password'  => password_hash((string) $payload['password'], PASSWORD_DEFAULT),
            'role'      => (string) $payload['role'],
            'is_active' => (int) ($payload['is_active'] ?? 1),
        ]);

        $newUserId = (int) $this->userModel->getInsertID();

        write_audit_log([
            'module'      => 'user_management',
            'action'      => 'create_user_api',
            'description' => 'Menambahkan user baru melalui API.',
            'target_type' => 'user',
            'target_id'   => $newUserId,
            'properties'  => [
                'name'      => (string) $payload['name'],
                'email'     => (string) $payload['email'],
                'role'      => (string) $payload['role'],
                'is_active' => (bool) ($payload['is_active'] ?? 1),
            ],
        ]);

        return $this->response->setStatusCode(201)->setJSON([
            'status'  => 'ok',
            'message' => 'User berhasil ditambahkan.',
            'data'    => $this->transformUser($this->findUserOrFail($newUserId)),
        ]);
    }

    public function update(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $user = $this->findUserOrFail($id);
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $rules = $this->validationRules($id, false);

        if (! $this->validateData($payload, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Data user tidak valid.',
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $data = [
            'name'      => (string) $payload['name'],
            'email'     => (string) $payload['email'],
            'role'      => (string) $payload['role'],
            'is_active' => (int) ($payload['is_active'] ?? 1),
        ];

        $password = (string) ($payload['password'] ?? '');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ((int) current_user('id') === (int) $user['id']) {
            $data['is_active'] = 1;
        }

        $this->userModel->update($id, $data);

        write_audit_log([
            'module'      => 'user_management',
            'action'      => 'update_user_api',
            'description' => 'Memperbarui data user melalui API.',
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
                    'name'             => $data['name'],
                    'email'            => $data['email'],
                    'role'             => $data['role'],
                    'is_active'        => $data['is_active'],
                    'password_changed' => array_key_exists('password', $data),
                ],
            ],
        ]);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'User berhasil diperbarui.',
            'data'    => $this->transformUser($this->findUserOrFail($id)),
        ]);
    }

    public function delete(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $user = $this->findUserOrFail($id);

        if ((int) current_user('id') === (int) $user['id']) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Akun yang sedang dipakai tidak bisa dihapus.',
            ]);
        }

        write_audit_log([
            'module'      => 'user_management',
            'action'      => 'delete_user_api',
            'description' => 'Menghapus user melalui API.',
            'target_type' => 'user',
            'target_id'   => $id,
            'properties'  => [
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);

        $this->userModel->delete($id);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'User berhasil dihapus.',
        ]);
    }

    private function findUserOrFail(int $id): array
    {
        $user = $this->userModel->find($id);

        if ($user === null) {
            throw PageNotFoundException::forPageNotFound('User tidak ditemukan.');
        }

        return $user;
    }

    private function transformUser(array $user): array
    {
        return [
            'id'            => (int) $user['id'],
            'name'          => $user['name'],
            'email'         => $user['email'],
            'role'          => $user['role'],
            'is_active'     => (bool) $user['is_active'],
            'last_login_at' => $this->formatDateTime($user['last_login_at'] ?? null),
            'created_at'    => $this->formatDateTime($user['created_at'] ?? null),
            'updated_at'    => $this->formatDateTime($user['updated_at'] ?? null),
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
