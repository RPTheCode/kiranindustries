<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPosting extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'requisition_id',
        'job_code',
        'title',
        'job_type_id',
        'location_id',
        'department_id',
        'min_experience',
        'max_experience',
        'min_salary',
        'max_salary',
        'description',
        'requirements',
        'benefits',
        'application_deadline',
        'is_published',
        'publish_date',
        'is_featured',
        'status',
        'created_by'
    ];

    protected $casts = [
        'application_deadline' => 'date',
        'publish_date' => 'datetime',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
    ];

    public function requisition()
    {
        return $this->belongsTo(JobRequisition::class);
    }

    public function jobType()
    {
        return $this->belongsTo(JobType::class);
    }

    public function location()
    {
        return $this->belongsTo(JobLocation::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class, 'job_id');
    }
}