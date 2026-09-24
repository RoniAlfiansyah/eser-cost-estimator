<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class AuthController extends BaseController
{
    public function login(): string
    {
        if (is_logged_in()) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login', [
            'title' => 'Login',
        ]);
    }

    public function attemptLogin()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userModel = new UserModel();
        $user = $userModel->findByEmail($this->request->getPost('email'));

        if ($user === null || ! password_verify((string) $this->request->getPost('password'), $user['password'])) {
            return redirect()->back()->withInput()->with('error', 'Email atau password tidak valid.');
        }

        if (! (bool) $user['is_active']) {
            return redirect()->back()->withInput()->with('error', 'Akun ini tidak aktif.');
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
            'action'      => 'login',
            'description' => 'User berhasil login ke sistem.',
            'target_type' => 'user',
            'target_id'   => $user['id'],
            'properties'  => [
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);

        return redirect()->to('/dashboard')->with('success', 'Login berhasil.');
    }

    public function logout()
    {
        $authUser = current_user();

        if ($authUser !== []) {
            write_audit_log([
                'user_id'     => $authUser['id'] ?? null,
                'module'      => 'authentication',
                'action'      => 'logout',
                'description' => 'User logout dari sistem.',
                'target_type' => 'user',
                'target_id'   => $authUser['id'] ?? null,
                'properties'  => [
                    'email' => $authUser['email'] ?? null,
                    'role'  => $authUser['role'] ?? null,
                ],
            ]);
        }

        $this->session->remove('auth_user');
        $this->session->destroy();

        return redirect()->to('/login')->with('success', 'Anda telah logout.');
    }
}
