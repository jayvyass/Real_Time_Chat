<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Get the selected role from the request
        $role = $this->input('role');

        // Conditionally validate the password based on the role
        $rules = [
            'email' => 'required|string|email',
        ];

        if ($role !== 'guest') {
            $rules['password'] = 'required|string';
        }

        return $rules;
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate()
    {
        $this->ensureIsNotRateLimited();

        $role = $this->input('role');

        // Skip password check if the role is 'guest'
        if ($role === 'guest') {
            $user = Auth::getProvider()->retrieveByCredentials(['email' => $this->input('email')]);

            if ($user && $user->role === 'guest') {
                Auth::login($user);
                return;
            }

            // If user is not found or role is incorrect, fail authentication
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        } else {
            // For non-guest roles, use the default password authentication
            if (! Auth::attempt($this->only('email', 'password'), $this->filled('remember'))) {
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }

            RateLimiter::clear($this->throttleKey());
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited()
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * @return string
     */
    public function throttleKey()
    {
        return Str::lower($this->input('email')).'|'.$this->ip();
    }
}
