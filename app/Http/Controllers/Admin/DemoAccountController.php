<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ScenarioBuilder;

/** Daftar akun demo untuk Admin Sistem. Hanya tersedia di lingkungan lokal/non-produksi. */
class DemoAccountController extends Controller
{
    public function index()
    {
        abort_if(app()->environment('production'), 404);

        $users = User::query()->where('email', 'like', '%@ehibah.test')->orderBy('email')->get();

        return view('admin.demo-accounts', ['users' => $users, 'password' => ScenarioBuilder::password()]);
    }
}