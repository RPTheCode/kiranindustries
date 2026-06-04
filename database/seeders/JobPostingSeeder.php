<?php

namespace Database\Seeders;

use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\JobType;
use App\Models\JobLocation;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class JobPostingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all companies
        $companies = User::where('type', 'company')->get();

        if ($companies->isEmpty()) {
            $this->command->warn('No company users found. Please run DefaultCompanySeeder first.');
            return;
        }

        $currentYear = date('Y');

        // Fixed job posting data
        $jobPostingData = [
            [
                'job_type' => 'Full-time',
                'location' => 'Head Office - Mumbai',
                'min_experience' => 5,
                'max_experience' => 8,
                'status' => 'Published',
                'is_published' => true,
                'is_featured' => true,
                'benefits' => 'Health insurance, Provident fund, Performance bonus, Flexible working hours, Professional development opportunities'
            ],
            [
                'job_type' => 'Full-time',
                'location' => 'Tech Hub - Bangalore',
                'min_experience' => 2,
                'max_experience' => 5,
                'status' => 'Published',
                'is_published' => true,
                'is_featured' => false,
                'benefits' => 'Medical coverage, Annual leave, Training programs, Career advancement, Work-life balance'
            ],
            [
                'job_type' => 'Remote',
                'location' => 'Remote Work - India',
                'min_experience' => 3,
                'max_experience' => 6,
                'status' => 'Draft',
                'is_published' => false,
                'is_featured' => false,
                'benefits' => 'Remote work allowance, Health benefits, Flexible schedule, Professional development, Equipment provided'
            ],
            [
                'job_type' => 'Full-time',
                'location' => 'Branch Office - Delhi',
                'min_experience' => 4,
                'max_experience' => 7,
                'status' => 'Published',
                'is_published' => true,
                'is_featured' => true,
                'benefits' => 'Comprehensive health plan, Retirement benefits, Performance incentives, Learning opportunities'
            ],
            [
                'job_type' => 'Contract',
                'location' => 'Development Center - Pune',
                'min_experience' => 1,
                'max_experience' => 3,
                'status' => 'Published',
                'is_published' => true,
                'is_featured' => false,
                'benefits' => 'Competitive compensation, Project completion bonus, Skill development programs'
            ],
            [
                'job_type' => 'Hybrid',
                'location' => 'Regional Office - Chennai',
                'min_experience' => 3,
                'max_experience' => 5,
                'status' => 'Closed',
                'is_published' => false,
                'is_featured' => false,
                'benefits' => 'Hybrid work model, Health insurance, Annual bonus, Career growth opportunities'
            ],
            [
                'job_type' => 'Part-time',
                'location' => 'Service Center - Hyderabad',
                'min_experience' => 1,
                'max_experience' => 2,
                'status' => 'Published',
                'is_published' => true,
                'is_featured' => false,
                'benefits' => 'Flexible hours, Health coverage, Skill training, Part-time benefits'
            ],
            [
                'job_type' => 'Internship',
                'location' => 'Tech Hub - Bangalore',
                'min_experience' => 0,
                'max_experience' => 1,
                'status' => 'Published',
                'is_published' => true,
                'is_featured' => true,
                'benefits' => 'Learning opportunities, Mentorship program, Stipend, Certificate of completion'
            ]
        ];

        foreach ($companies as $company) {
            // Get job requisitions for this company
            $jobRequisitions = JobRequisition::where('created_by', $company->id)->get();

            if ($jobRequisitions->isEmpty()) {
                $this->command->warn('No job requisitions found for company: ' . $company->name . '. Please run JobRequisitionSeeder first.');
                continue;
            }

            // Get job types, locations, and departments for this company
            $jobTypes = JobType::where('created_by', $company->id)->get();
            $jobLocations = JobLocation::where('created_by', $company->id)->get();
            $departments = Department::where('created_by', $company->id)->get();

            if ($jobTypes->isEmpty() || $jobLocations->isEmpty()) {
                $this->command->warn('Missing job types or locations for company: ' . $company->name);
                continue;
            }

            $postingCounter = ($company->id - 1) * 100 + 1;

            // Create job postings for first 8 requisitions
            $selectedRequisitions = $jobRequisitions->take(8);

            foreach ($selectedRequisitions as $index => $requisition) {
                $postingData = $jobPostingData[$index % 8];
                $jobCode = 'JOB-' . $currentYear . '-' . str_pad($postingCounter, 4, '0', STR_PAD_LEFT);

                // Check if job posting already exists
                if (JobPosting::where('job_code', $jobCode)->exists()) {
                    $postingCounter++;
                    continue;
                }

                // Find matching job type and location
                $jobType = $jobTypes->where('name', $postingData['job_type'])->first();
                $jobLocation = $jobLocations->where('name', $postingData['location'])->first();

                // Fallback to first available if not found
                if (!$jobType) $jobType = $jobTypes->first();
                if (!$jobLocation) $jobLocation = $jobLocations->first();

                // Select department
                $selectedDepartments = $departments->take(5);
                $department = $selectedDepartments->isNotEmpty() ? $selectedDepartments->first() : null;

                $applicationDeadline = $postingData['status'] === 'Published' ?
                    date('Y-m-d', strtotime('+30 days')) : null;

                $publishDate = $postingData['is_published'] ? now() : null;

                try {
                    JobPosting::create([
                        'requisition_id' => $requisition->id,
                        'job_code' => $jobCode,
                        'title' => $requisition->title,
                        'job_type_id' => $jobType->id,
                        'location_id' => $jobLocation->id,
                        'department_id' => $department?->id,
                        'min_experience' => $postingData['min_experience'],
                        'max_experience' => $postingData['max_experience'],
                        'min_salary' => $requisition->budget_min,
                        'max_salary' => $requisition->budget_max,
                        'description' => $requisition->description,
                        'requirements' => $requisition->skills_required . '. ' . $requisition->education_required . '. ' . $requisition->experience_required,
                        'benefits' => $postingData['benefits'],
                        'application_deadline' => $applicationDeadline,
                        'is_published' => $postingData['is_published'],
                        'publish_date' => $publishDate,
                        'is_featured' => $postingData['is_featured'],
                        'status' => $postingData['status'],
                        'created_by' => $company->id,
                    ]);

                    $postingCounter++;
                } catch (\Exception $e) {
                    $this->command->error('Failed to create job posting for requisition: ' . $requisition->title . ' in company: ' . $company->name);
                    $postingCounter++;
                    continue;
                }
            }
        }

        $this->command->info('JobPosting seeder completed successfully!');
    }
}
