<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Show list of users.
     */
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);
        $registrationEnabled = Setting::getValue('system.registration_enabled', false);
        
        return view('admin.users.index', compact('users', 'registrationEnabled'));
    }

    /**
     * Toggle user registration setting.
     */
    public function toggleRegistration(Request $request)
    {
        $enabled = $request->boolean('enabled');
        Setting::setValue('system.registration_enabled', $enabled, 'boolean', 'system');

        return back()->with('success', $enabled 
            ? __('User registration has been enabled.') 
            : __('User registration has been disabled.')
        );
    }

    /**
     * Show create user form.
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Store new user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['nullable'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $request->boolean('is_admin'),
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show edit user form.
     */
    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_admin' => ['nullable'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Only allow changing is_admin when editing another user. When editing yourself, the
        // checkbox is disabled (so it is not submitted) and we must preserve your admin status.
        if ($user->id !== auth()->id()) {
            $user->is_admin = $request->boolean('is_admin');
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Delete user.
     */
    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
