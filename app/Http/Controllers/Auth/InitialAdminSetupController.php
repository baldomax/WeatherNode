<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class InitialAdminSetupController extends Controller
{
    /**
     * Show the first-run admin setup form.
     */
    public function create(): View
    {
        abort_unless(dockerAdminSetupPending(), 404);

        return view('auth.initial-admin-setup');
    }

    /**
     * Create the first admin account on a fresh install.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(dockerAdminSetupPending(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => true,
        ]);

        return redirect()->route('login')
            ->with('status', __('Admin account created. You can now log in.'));
    }
}
