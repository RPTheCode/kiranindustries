<?php
namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $routeUser = $this->route('user');
        $userId = match (true) {
            $routeUser instanceof User => $routeUser->id,
            is_numeric($routeUser) => (int) $routeUser,
            default => null,
        };

        return [
            'name' => 'required|string',
            'email' => [
                'required',
                'strict_email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => $this->isMethod('POST') ? 'required|string|min:6' : 'nullable|string|min:6',
            'password_confirmation' => $this->isMethod('POST')
                ? 'required|same:password'
                : 'nullable|required_with:password|same:password',
            'roles' => 'required',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
            'employee_code' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
        ];
    }
}
