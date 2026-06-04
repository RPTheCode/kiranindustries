<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'source_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'current_company',
        'current_position',
        'experience_years',
        'current_salary',
        'expected_salary',
        'notice_period',
        'resume_path',
        'cover_letter_path',
        'skills',
        'education',
        'portfolio_url',
        'linkedin_url',
        'referral_employee_id',
        'status',
        'application_date',
        'created_by'
    ];

    protected $casts = [
        'application_date' => 'date',
        'current_salary' => 'decimal:2',
        'expected_salary' => 'decimal:2',
    ];

    public function job()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function source()
    {
        return $this->belongsTo(CandidateSource::class);
    }

    public function referralEmployee()
    {
        return $this->belongsTo(User::class, 'referral_employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function assessments()
    {
        return $this->hasMany(CandidateAssessment::class);
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}