<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminLoginController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if ($request->password !== config('app.admin_password')) {
            return back()->withErrors(['password' => 'Nesprávne heslo.']);
        }

        $request->session()->put('admin_logged_in', true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        $request->session()->forget('admin_logged_in');
        $request->session()->regenerate();

        return redirect()->route('login');
    }
}
