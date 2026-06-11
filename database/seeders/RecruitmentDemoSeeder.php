<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\CandidateSource;
use App\Models\Department;
use App\Models\Interview;
use App\Models\InterviewRound;
use App\Models\InterviewType;
use App\Models\JobCategory;
use App\Models\JobLocation;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\JobType;
use App\Models\Offer;
use App\Models\OfferTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Kiran Industries — Recruitment demo data (easy to understand).
 *
 * Run:
 *   php artisan db:seed --class=RecruitmentDemoSeeder
 *
 * What it creates (see console output for mapping):
 *   Settings → Categories, Job Types, Locations, Sources, Interview Types
 *   Jobs     → 1 approved requisition + 1 published job posting
 *   Pipeline → 6 sample candidates (New → Hired)
 *   Interviews + Offers for demo candidates
 */
class RecruitmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = User::where('type', 'company')->first();

        if (! $company) {
            $this->command->error('No company user found. Run DefaultCompanySeeder first.');

            return;
        }

        $companyId = $company->id;
        $companyUserIds = User::where('created_by', $companyId)->pluck('id')->push($companyId)->all();
        $department = Department::whereIn('created_by', $companyUserIds)->first();

        $this->command->info('');
        $this->command->info('=== RECRUITMENT DEMO SEED — Kiran Industries style examples ===');
        $this->command->info('Company: ' . $company->name . ' (ID: ' . $companyId . ')');
        $this->command->info('');

        // ─────────────────────────────────────────────────────────────
        // STEP 1: SETTINGS (Recruitment → Settings tabs)
        // ─────────────────────────────────────────────────────────────
        $this->command->info('STEP 1 — Settings master data (Categories | Job Types | Locations | Sources | Interview Types)');

        $categories = [
            ['name' => 'Production', 'description' => 'Factory floor, machine operators, shift supervisors'],
            ['name' => 'Quality Control', 'description' => 'QC inspectors, QA engineers, lab testing'],
            ['name' => 'Maintenance', 'description' => 'Electricians, fitters, plant maintenance'],
            ['name' => 'Administration', 'description' => 'HR, accounts, office staff'],
        ];
        foreach ($categories as $row) {
            JobCategory::firstOrCreate(
                ['name' => $row['name'], 'created_by' => $companyId],
                ['description' => $row['description'], 'status' => 'active']
            );
            $this->command->line('  [Category] ' . $row['name']);
        }

        $jobTypes = [
            ['name' => 'Full-time', 'description' => 'Permanent staff on company payroll'],
            ['name' => 'Contract', 'description' => 'Fixed-term contract workers'],
            ['name' => 'Trainee', 'description' => 'ITI / on-the-job training roles'],
        ];
        foreach ($jobTypes as $row) {
            JobType::firstOrCreate(
                ['name' => $row['name'], 'created_by' => $companyId],
                ['description' => $row['description'], 'status' => 'active']
            );
            $this->command->line('  [Job Type] ' . $row['name']);
        }

        $locations = [
            [
                'name' => 'Palsana Plant',
                'address' => 'GIDC Palsana',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'country' => 'India',
                'postal_code' => '394305',
                'is_remote' => false,
            ],
            [
                'name' => 'Surat Head Office',
                'address' => 'Ring Road',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'country' => 'India',
                'postal_code' => '395002',
                'is_remote' => false,
            ],
            [
                'name' => 'On-site Only',
                'address' => null,
                'city' => null,
                'state' => null,
                'country' => 'India',
                'postal_code' => null,
                'is_remote' => false,
            ],
        ];
        foreach ($locations as $row) {
            JobLocation::firstOrCreate(
                ['name' => $row['name'], 'created_by' => $companyId],
                array_merge($row, ['status' => 'active'])
            );
            $this->command->line('  [Location] ' . $row['name']);
        }

        $sources = [
            ['name' => 'Walk-in Interview', 'description' => 'Candidate came to factory gate / HR desk'],
            ['name' => 'Employee Referral', 'description' => 'Referred by existing employee'],
            ['name' => 'Naukri.com', 'description' => 'Job portal application'],
            ['name' => 'ITI Campus', 'description' => 'Campus drive at local ITI'],
            ['name' => 'Consultant', 'description' => 'Labour / recruitment consultant'],
        ];
        foreach ($sources as $row) {
            CandidateSource::firstOrCreate(
                ['name' => $row['name'], 'created_by' => $companyId],
                ['description' => $row['description'], 'status' => 'active']
            );
            $this->command->line('  [Source] ' . $row['name']);
        }

        $interviewTypes = [
            ['name' => 'HR Round', 'description' => 'Basic details, salary expectation, joining date'],
            ['name' => 'Supervisor Round', 'description' => 'Department head / shift in-charge interview'],
            ['name' => 'Practical Test', 'description' => 'Machine handling or trade skill test on shop floor'],
            ['name' => 'Final Approval', 'description' => 'Plant manager / HR manager sign-off'],
        ];
        foreach ($interviewTypes as $row) {
            InterviewType::firstOrCreate(
                ['name' => $row['name'], 'created_by' => $companyId],
                ['description' => $row['description'], 'status' => 'active']
            );
            $this->command->line('  [Interview Type] ' . $row['name']);
        }

        // ─────────────────────────────────────────────────────────────
        // STEP 2: JOB REQUISITION + POSTING (Recruitment → Jobs)
        // ─────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('STEP 2 — Jobs (Requisition → Approved → Job Posting → Published)');

        $productionCategory = JobCategory::where('name', 'Production')->where('created_by', $companyId)->first();
        $fullTime = JobType::where('name', 'Full-time')->where('created_by', $companyId)->first();
        $palsana = JobLocation::where('name', 'Palsana Plant')->where('created_by', $companyId)->first();

        $requisition = JobRequisition::firstOrCreate(
            ['requisition_code' => 'REQ-KI-2026-001'],
            [
                'title' => 'CNC Machine Operator',
                'job_category_id' => $productionCategory->id,
                'department_id' => $department?->id,
                'positions_count' => 3,
                'budget_min' => 18000,
                'budget_max' => 28000,
                'skills_required' => 'CNC operating, blueprint reading, micrometer/vernier',
                'education_required' => 'ITI Fitter / Machinist or 12th with 2 years experience',
                'experience_required' => '1–4 years in manufacturing',
                'description' => 'Hiring CNC operators for day shift at Palsana plant.',
                'responsibilities' => 'Operate CNC, daily machine checklist, report breakdown to maintenance',
                'status' => 'Approved',
                'priority' => 'High',
                'created_by' => $companyId,
            ]
        );
        $this->command->line('  [Requisition] ' . $requisition->title . ' — Status: Approved');

        $posting = JobPosting::firstOrCreate(
            ['job_code' => 'JOB-KI-2026-001'],
            [
                'requisition_id' => $requisition->id,
                'title' => 'CNC Machine Operator — Palsana',
                'job_type_id' => $fullTime->id,
                'location_id' => $palsana->id,
                'department_id' => $department?->id,
                'min_experience' => 1,
                'max_experience' => 4,
                'min_salary' => 18000,
                'max_salary' => 28000,
                'description' => 'Join Kiran Industries production team. Accommodation near plant available.',
                'requirements' => 'ITI preferred. Must pass practical machine test.',
                'benefits' => 'PF, ESIC, overtime, canteen, bus facility',
                'application_deadline' => now()->addDays(30)->toDateString(),
                'is_published' => true,
                'publish_date' => now(),
                'is_featured' => true,
                'status' => 'Published',
                'created_by' => $companyId,
            ]
        );
        $this->command->line('  [Job Posting] ' . $posting->title . ' — Published: Yes');

        $rounds = [
            ['name' => 'HR Screening', 'sequence_number' => 1],
            ['name' => 'Supervisor Interview', 'sequence_number' => 2],
            ['name' => 'Practical on Machine', 'sequence_number' => 3],
        ];
        foreach ($rounds as $round) {
            InterviewRound::firstOrCreate(
                ['job_id' => $posting->id, 'name' => $round['name'], 'created_by' => $companyId],
                [
                    'sequence_number' => $round['sequence_number'],
                    'description' => $round['name'] . ' for ' . $posting->title,
                    'status' => 'active',
                ]
            );
            $this->command->line('  [Interview Round] ' . $round['name']);
        }

        // ─────────────────────────────────────────────────────────────
        // STEP 3: CANDIDATES (Recruitment → Candidates pipeline)
        // ─────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('STEP 3 — Candidates (each status = one column in Pipeline board)');

        $walkIn = CandidateSource::where('name', 'Walk-in Interview')->where('created_by', $companyId)->first();
        $naukri = CandidateSource::where('name', 'Naukri.com')->where('created_by', $companyId)->first();
        $referral = CandidateSource::where('name', 'Employee Referral')->where('created_by', $companyId)->first();
        $iti = CandidateSource::where('name', 'ITI Campus')->where('created_by', $companyId)->first();

        $candidates = [
            [
                'first_name' => 'Ramesh', 'last_name' => 'Patel', 'email' => 'ramesh.patel.demo@example.com',
                'phone' => '9876501001', 'experience_years' => 0, 'status' => 'New',
                'source_id' => $iti->id, 'current_company' => null, 'current_position' => 'ITI Fitter (final year)',
            ],
            [
                'first_name' => 'Suresh', 'last_name' => 'Makwana', 'email' => 'suresh.makwana.demo@example.com',
                'phone' => '9876501002', 'experience_years' => 2, 'status' => 'Screening',
                'source_id' => $walkIn->id, 'current_company' => 'Local workshop', 'current_position' => 'Lathe operator',
            ],
            [
                'first_name' => 'Mahesh', 'last_name' => 'Rathod', 'email' => 'mahesh.rathod.demo@example.com',
                'phone' => '9876501003', 'experience_years' => 3, 'status' => 'Interview',
                'source_id' => $naukri->id, 'current_company' => 'ABC Engineering', 'current_position' => 'CNC Operator',
            ],
            [
                'first_name' => 'Kiran', 'last_name' => 'Solanki', 'email' => 'kiran.solanki.demo@example.com',
                'phone' => '9876501004', 'experience_years' => 4, 'status' => 'Offer',
                'source_id' => $referral->id, 'current_company' => 'XYZ Metals', 'current_position' => 'Senior Operator',
            ],
            [
                'first_name' => 'Dinesh', 'last_name' => 'Chauhan', 'email' => 'dinesh.chauhan.demo@example.com',
                'phone' => '9876501005', 'experience_years' => 2, 'status' => 'Hired',
                'source_id' => $walkIn->id, 'current_company' => 'Self employed', 'current_position' => 'Machinist',
            ],
            [
                'first_name' => 'Prakash', 'last_name' => 'Vaghela', 'email' => 'prakash.vaghela.demo@example.com',
                'phone' => '9876501006', 'experience_years' => 1, 'status' => 'Rejected',
                'source_id' => $naukri->id, 'current_company' => null, 'current_position' => 'Fresher',
            ],
        ];

        $createdCandidates = [];
        foreach ($candidates as $row) {
            $candidate = Candidate::firstOrCreate(
                ['email' => $row['email'], 'created_by' => $companyId],
                [
                    'job_id' => $posting->id,
                    'source_id' => $row['source_id'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'current_company' => $row['current_company'],
                    'current_position' => $row['current_position'],
                    'experience_years' => $row['experience_years'],
                    'expected_salary' => 22000,
                    'notice_period' => '15 days',
                    'skills' => 'CNC, Vernier caliper, basic G-code',
                    'education' => 'ITI Fitter',
                    'status' => $row['status'],
                    'application_date' => now()->subDays(rand(3, 20))->toDateString(),
                ]
            );
            $createdCandidates[$row['status']] = $candidate;
            $this->command->line('  [Candidate] ' . $candidate->full_name . ' → Pipeline: ' . $row['status']);
        }

        // ─────────────────────────────────────────────────────────────
        // STEP 4: INTERVIEW (Recruitment → Interviews)
        // ─────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('STEP 4 — Interview scheduled for candidate in "Interview" stage');

        $interviewCandidate = $createdCandidates['Interview'] ?? null;
        $hrRound = InterviewRound::where('job_id', $posting->id)->where('name', 'HR Screening')->first();
        $hrType = InterviewType::where('name', 'HR Round')->where('created_by', $companyId)->first();
        $manager = User::whereIn('type', ['manager', 'hr', 'company'])->where('created_by', $companyId)->first();

        if ($interviewCandidate && $hrRound && $hrType) {
            Interview::firstOrCreate(
                [
                    'candidate_id' => $interviewCandidate->id,
                    'round_id' => $hrRound->id,
                    'scheduled_date' => now()->addDays(2)->toDateString(),
                ],
                [
                    'job_id' => $posting->id,
                    'interview_type_id' => $hrType->id,
                    'scheduled_time' => '11:00',
                    'duration' => 45,
                    'location' => 'Palsana Plant — HR Office',
                    'meeting_link' => null,
                    'interviewers' => $manager ? [(string) $manager->id] : [],
                    'status' => 'Scheduled',
                    'feedback_submitted' => false,
                    'created_by' => $companyId,
                ]
            );
            $this->command->line('  [Interview] ' . $interviewCandidate->full_name . ' on ' . now()->addDays(2)->format('d M Y') . ' 11:00 AM');
        }

        // ─────────────────────────────────────────────────────────────
        // STEP 5: OFFERS (Recruitment → Offers & Selection)
        // ─────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('STEP 5 — Offers & offer letter template');

        OfferTemplate::firstOrCreate(
            ['name' => 'Standard Appointment Letter', 'created_by' => $companyId],
            [
                'template_content' => "Dear {{candidate_name}},\n\nWe are pleased to offer you the position of {{position}} at Kiran Industries.\nJoining Date: {{joining_date}}\nCTC: {{salary}} per month.\n\nRegards,\nHR Team",
                'variables' => ['candidate_name', 'position', 'joining_date', 'salary'],
                'status' => 'active',
            ]
        );
        $this->command->line('  [Offer Template] Standard Appointment Letter');

        foreach (['Offer', 'Hired'] as $offerStatus) {
            $candidate = $createdCandidates[$offerStatus] ?? null;
            if (! $candidate) {
                continue;
            }

            Offer::firstOrCreate(
                ['candidate_id' => $candidate->id, 'created_by' => $companyId],
                [
                    'job_id' => $posting->id,
                    'offer_date' => now()->toDateString(),
                    'position' => 'CNC Machine Operator',
                    'department_id' => $department?->id,
                    'salary' => 24000,
                    'bonus' => 2000,
                    'benefits' => 'PF, ESIC, canteen, bus',
                    'start_date' => now()->addDays(15)->toDateString(),
                    'expiration_date' => now()->addDays(7)->toDateString(),
                    'status' => $offerStatus === 'Hired' ? 'Accepted' : 'Sent',
                    'response_date' => $offerStatus === 'Hired' ? now()->toDateString() : null,
                    'approved_by' => $manager?->id,
                ]
            );
            $this->command->line('  [Offer] ' . $candidate->full_name . ' — ₹24,000/month (' . ($offerStatus === 'Hired' ? 'Accepted' : 'Sent') . ')');
        }

        // ─────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('=== DONE! Open these pages to see data ===');
        $this->command->table(
            ['Screen in software', 'URL'],
            [
                ['Settings → Branch', '/recruitment/settings?tab=branches'],
                ['Settings → Categories', '/recruitment/settings?tab=categories'],
                ['Jobs', '/recruitment/jobs'],
                ['Candidates Pipeline', '/recruitment/candidates'],
                ['Interviews', '/recruitment/interviews'],
                ['Offers → Employee Selection', '/recruitment/offers?tab=selection'],
                ['Recruitment Hub', '/recruitment'],
            ]
        );
        $this->command->info('');
    }
}
