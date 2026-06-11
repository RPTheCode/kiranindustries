<?php

namespace App\Services\Mobile;

use App\Http\Resources\Mobile\EmployeeProfileResource;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;

class MobileAuthPayloadBuilder
{
    public function __construct(
        private MobileMenuBuilder $menuBuilder
    ) {}

    public function build(User $user): array
    {
        $employee = mobileUserEmployee($user);

        if ($employee) {
            $employee->loadMissing([
                'branch',
                'department',
                'designation',
                'shift.slots',
                'category',
            ]);
        }

        return [
            'user' => new UserResource($user),
            'employee' => $employee ? new EmployeeProfileResource($employee) : null,
            'menu' => $this->menuBuilder->build($user),
        ];
    }
}
