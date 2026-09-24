<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\I18n\Time;

class PingController extends BaseController
{
    public function index()
    {
        return $this->response->setJSON([
            'status'      => 'ok',
            'message'     => 'Cost Estimator backend API is reachable.',
            'environment' => ENVIRONMENT,
            'timestamp'   => Time::now()->toDateTimeString(),
        ]);
    }
}
