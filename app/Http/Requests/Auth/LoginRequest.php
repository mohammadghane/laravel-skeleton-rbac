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
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accepts username OR email in one field, same identifiers the old
            // UserController::isValid() treated as unique login handles.
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => __('نام کاربری یا ایمیل را وارد کنید.'),
            'password.required' => __('کلمه عبور را وارد کنید.'),
        ];
    }

    /**
     * Attempt to authenticate the request's credentials, with lockout protection
     * (5 attempts per minute, keyed by identifier + IP — mirrors typical brute-force
     * protection that wasn't present in the old CI3 code but should be here).
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $field = filter_var($this->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $this->input('login'),
            'password' => $this->input('password'),
        ];

        if (!Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('نام کاربری/ایمیل یا کلمه عبور اشتباه است.'),
            ]);
        }

        // Soft-deleted / deactivated accounts shouldn't be able to log in even if the
        // password matches (old code used a boolean `delete` column for this check).
        if (Auth::user()->trashed()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'login' => __('این حساب کاربری غیرفعال شده است.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => __('تعداد تلاش های ورود بیش از حد مجاز است. لطفا :seconds ثانیه دیگر تلاش کنید.', ['seconds' => $seconds]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('login')) . '|' . $this->ip());
    }
}
