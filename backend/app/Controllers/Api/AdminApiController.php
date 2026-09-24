<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;

abstract class AdminApiController extends BaseController
{
    protected function ensureAdmin()
    {
        helper('auth');

        if (current_user('role') !== UserModel::ROLE_ADMIN) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki akses ke resource ini.',
            ]);
        }

        return null;
    }

    protected function formatDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_object($value) && method_exists($value, 'toDateTimeString')) {
            return $value->toDateTimeString();
        }

        return (string) $value;
    }
}
