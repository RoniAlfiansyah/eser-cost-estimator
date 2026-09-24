<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $userModel = new UserModel();

        $stats = [
            'totalUsers'    => $userModel->countAllResults(),
            'activeUsers'   => $userModel->where('is_active', 1)->countAllResults(),
            'adminUsers'    => $userModel->where('role', UserModel::ROLE_ADMIN)->countAllResults(),
            'managerUsers'  => $userModel->where('role', UserModel::ROLE_MANAGER)->countAllResults(),
            'staffUsers'    => $userModel->where('role', UserModel::ROLE_STAFF)->countAllResults(),
        ];

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'user'  => current_user(),
            'stats' => $stats,
        ]);
    }
}
