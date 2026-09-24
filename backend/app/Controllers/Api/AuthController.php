<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class AuthController extends BaseController
{
    public function login()
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Data login tidak valid.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $userModel = new UserModel();
        $user = $userModel->findByEmail((string) $payload['email']);

        if ($user === null || ! password_verify((string) $payload['password'], $user['password'])) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Email atau password tidak valid.',
                ]);
        }

        if (! (bool) $user['is_active']) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Akun ini tidak aktif.',
                ]);
        }

        $userModel->update($user['id'], [
            'last_login_at' => Time::now(),
        ]);

        $this->session->regenerate();
        $this->session->set([
            'auth_user' => [
                'id'       => (int) $user['id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'loggedIn' => true,
            ],
        ]);

        write_audit_log([
            'user_id'     => $user['id'],
            'module'      => 'authentication',
            'action'      => 'login_api',
            'description' => 'User berhasil login melalui API.',
            'target_type' => 'user',
            'target_id'   => $user['id'],
            'properties'  => [
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Login berhasil.',
            'user'    => current_user(),
        ]);
    }

    public function register()
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Data registrasi tidak valid.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $userModel = new UserModel();
        $isFirstUser = $userModel->countAllResults() === 0;

        $name = $payload['name'] ?? strstr((string) $payload['email'], '@', true);
        if ($name === false || $name === null || trim((string) $name) === '') {
            $name = 'Administrator';
        }

        $role = $isFirstUser ? UserModel::ROLE_ADMIN : UserModel::ROLE_STAFF;
        $isActive = 1;

        $userModel->insert([
            'name'      => trim((string) $name),
            'email'     => (string) $payload['email'],
            'password'  => password_hash((string) $payload['password'], PASSWORD_DEFAULT),
            'role'      => $role,
            'is_active' => $isActive,
        ]);

        $newUserId = (int) $userModel->getInsertID();

        write_audit_log([
            'user_id'     => $newUserId,
            'module'      => 'authentication',
            'action'      => 'register_api',
            'description' => $isFirstUser
                ? 'User pertama mendaftar dan dijadikan admin aktif.'
                : 'User baru mendaftar melalui API.',
            'target_type' => 'user',
            'target_id'   => $newUserId,
            'properties'  => [
                'email'      => (string) $payload['email'],
                'role'       => $role,
                'is_active'  => true,
                'is_first_user' => $isFirstUser,
            ],
        ]);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'status'  => 'ok',
                'message' => $isFirstUser
                    ? 'Registrasi berhasil. Akun pertama dijadikan admin dan langsung aktif.'
                    : 'Registrasi berhasil. Akun Anda sudah aktif.',
            ]);
    }

    public function me()
    {
        if (! is_logged_in()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'user'   => current_user(),
        ]);
    }

    public function logout()
    {
        if (is_logged_in()) {
            write_audit_log([
                'user_id'     => current_user('id'),
                'module'      => 'authentication',
                'action'      => 'logout_api',
                'description' => 'User logout melalui API.',
                'target_type' => 'user',
                'target_id'   => current_user('id'),
                'properties'  => [
                    'email' => current_user('email'),
                    'role'  => current_user('role'),
                ],
            ]);
        }

        $this->session->remove('auth_user');
        $this->session->destroy();

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Logout berhasil.',
        ]);
    }
}
