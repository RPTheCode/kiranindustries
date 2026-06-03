<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? trim($this->email) : $this->email,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'strict_email'],
            'password' => ['required', 'string', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('Please enter your email address.'),
            'email.strict_email' => __('Please enter a valid email address.'),
            'password.required' => __('Please enter your password.'),
            'password.min' => __('Please enter your password.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('email address'),
            'password' => __('password'),
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = Str::lower((string) $this->input('email'));
        $password = (string) $this->input('password');

        $user = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            $this->failCredentials();
        }

        if ($user->trashed()) {
            $this->failCredentials(__('This account is no longer available. Please contact your administrator.'));
        }

        if (($user->status ?? 'active') === 'inactive') {
            throw ValidationException::withMessages([
                'login' => __('Your account is inactive. Please contact your administrator.'),
                'email' => __('Your account is inactive. Please contact your administrator.'),
            ]);
        }

        if (isset($user->is_enable_login) && (int) $user->is_enable_login === 0) {
            throw ValidationException::withMessages([
                'login' => __('Login is disabled for this account. Please contact your administrator.'),
                'email' => __('Login is disabled for this account. Please contact your administrator.'),
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            $this->failCredentials();
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failCredentials(?string $message = null): void
    {
        RateLimiter::hit($this->throttleKey());

        $message = $message ?? __('These credentials do not match our records. Please check your email and password.');

        throw ValidationException::withMessages([
            'login' => $message,
            'email' => $message,
            'password' => __('Please verify your password and try again.'),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $message = __('Too many login attempts. Please try again in :minutes minute(s).', [
            'minutes' => max(1, (int) ceil($seconds / 60)),
            'seconds' => $seconds,
        ]);

        throw ValidationException::withMessages([
            'login' => $message,
            'email' => $message,
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
