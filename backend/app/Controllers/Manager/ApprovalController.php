<?php

namespace App\Controllers\Manager;

use App\Controllers\BaseController;

class ApprovalController extends BaseController
{
    public function index(): string
    {
        return view('manager/approvals/index', [
            'title' => 'Approval Center',
        ]);
    }
}
