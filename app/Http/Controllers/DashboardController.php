<?php
namespace App\Http\Controllers;
use App\Models\AdminModel;
use App\Models\Promoter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index()
    {
        return view('pages.dashboard');
    }

    public function customize()
    {
        return view('pages.customize-settings');
    }

}