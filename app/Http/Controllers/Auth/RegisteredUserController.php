<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $role = $request->input ('role');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => $role === 'employee' ? 'required|string|confirmed|min:8' : 'nullable',
            'photo' => $role === 'employee' ? 'required|image|max:2048' : 'nullable|image|max:2048',
            'role'=> 'required'
        ]);

        // Handle the profile picture upload
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('profile-images', 'public');
        } elseif ($role === 'guest') {
            // Use a default profile picture for guests
            $photoPath = 'profile-images/guest.jpeg'; // Path to a default image with 'G'
        } else {
            $photoPath = null; // Default value if no photo is uploaded
        }
        // Default password for workers
        $password = $role === 'employee' ? Hash::make($request->password) : Hash::make(Str::random(10));

        // Create new user
        Auth::login($user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $password,
            'photo' => $photoPath, // Save the photo path
            'role' => $role,
        ]));

        event(new Registered($user));

        return redirect('dashboard');
    }
}
