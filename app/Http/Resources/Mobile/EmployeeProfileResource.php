<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class EmployeeProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shift = $this->shift;
        $firstSlot = $shift?->slots?->first();
        $lastSlot = $shift?->slots?->last();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'code' => $this->employee_id,
            'phone' => $this->phone,
            'branch' => $this->branch?->name,
            'branch_id' => $this->branch_id,
            'department' => $this->department?->name,
            'department_id' => $this->department_id,
            'designation' => $this->designation?->name,
            'designation_id' => $this->designation_id,
            'category' => $this->category?->name,
            'category_id' => $this->category_id,
            'date_of_joining' => $this->date_of_joining
                ? Carbon::parse($this->date_of_joining)->format('Y-m-d')
                : null,
            'shift' => $shift ? [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $firstSlot?->start_time,
                'end_time' => $lastSlot?->end_time,
            ] : null,
        ];
    }
}
