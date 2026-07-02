<?php

namespace App\Http\Controllers\superadmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(){

        return view('superadmin.dashboard.index');
    }
}
