<?php

namespace App\Controllers\Staff;

use App\Controllers\BaseController;

class ProjectController extends BaseController
{
    public function index(): string
    {
        return view('staff/projects/index', [
            'title' => 'Project Workspace',
        ]);
    }
}
