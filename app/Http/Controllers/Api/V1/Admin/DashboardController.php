<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Analytics\AdminDashboardService;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardService $dashboard): array
    {
        return $dashboard->overview();
    }
}
