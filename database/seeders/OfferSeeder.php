<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
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

        // Fixed offer data for different salary ranges
        $offerTemplates = [
            ['salary' => 120000, 'bonus' => 15000, 'equity' => '0.5% stock options', 'benefits' => 'Health insurance, Dental coverage, Vision care, 401k matching, Flexible PTO, Professional development budget', 'status' => 'Accepted', 'days_ago' => 5, 'expiry_days' => 7, 'start_days' => 14, 'response_days' => 2],
            ['salary' => 85000, 'bonus' => 8500, 'equity' => null, 'benefits' => 'Health insurance, Paid time off, Training programs, Performance bonuses', 'status' => 'Sent', 'days_ago' => 2, 'expiry_days' => 10, 'start_days' => 21, 'response_days' => null],
            ['salary' => 95000, 'bonus' => 12000, 'equity' => '0.2% stock options', 'benefits' => 'Comprehensive health package, Retirement benefits, Flexible working hours, Remote work options', 'status' => 'Negotiating', 'days_ago' => 3, 'expiry_days' => 14, 'start_days' => 28, 'response_days' => 1],
            ['salary' => 75000, 'bonus' => 5000, 'equity' => null, 'benefits' => 'Health insurance, Annual leave, Professional development opportunities', 'status' => 'Declined', 'days_ago' => 7, 'expiry_days' => 7, 'start_days' => 14, 'response_days' => 4, 'decline_reason' => 'Accepted offer from another company with better compensation package'],
            ['salary' => 110000, 'bonus' => 18000, 'equity' => '0.8% stock options', 'benefits' => 'Premium health coverage, Dental and vision, 401k with company match, Unlimited PTO, Gym membership', 'status' => 'Accepted', 'days_ago' => 10, 'expiry_days' => 14, 'start_days' => 30, 'response_days' => 6],
            ['salary' => 65000, 'bonus' => null, 'equity' => null, 'benefits' => 'Basic health insurance, Paid holidays, Training opportunities', 'status' => 'Expired', 'days_ago' => 20, 'expiry_days' => 7, 'start_days' => 14, 'response_days' => null],
            ['salary' => 135000, 'bonus' => 25000, 'equity' => '1.2% stock options', 'benefits' => 'Executive health plan, Car allowance, Club membership, Stock options, Flexible schedule', 'status' => 'Sent', 'days_ago' => 1, 'expiry_days' => 14, 'start_days' => 30, 'response_days' => null],
            ['salary' => 88000, 'bonus' => 10000, 'equity' => '0.3% stock options', 'benefits' => 'Health and dental insurance, Retirement plan, Professional development, Work from home options', 'status' => 'Draft', 'days_ago' => 0, 'expiry_days' => 10, 'start_days' => 21, 'response_days' => null]
        ];

        foreach ($companies as $company) {
            // Get hired candidates for this company
            $hiredCandidates = Candidate::where('created_by', $company->id)
                ->where('status', 'Hired')
                ->get();

            if ($hiredCandidates->isEmpty()) {
                $this->command->warn('No hired candidates found for company: ' . $company->name . '. Please run CandidateSeeder first.');
                continue;
            }

            // Get departments for this company
            $departments = Department::where('created_by', $company->id)->get();

            // Get managers/HR for approval
            $approvers = User::whereIn('type', ['manager', 'hr'])
                ->where('created_by', $company->id)
                ->get();

            // Create one offer per hired candidate
            foreach ($hiredCandidates as $index => $candidate) {
                // Check if offer already exists for this candidate in this company
                if (Offer::where('candidate_id', $candidate->id)->where('created_by', $company->id)->exists()) {
                    continue;
                }

                $offerTemplate = $offerTemplates[$index % count($offerTemplates)];

                // Select department from first 5
                $selectedDepartments = $departments->take(5);
                $department = $selectedDepartments->isNotEmpty() ? $selectedDepartments->first() : null;

                // Select approver
                $approver = $approvers->isNotEmpty() ? $approvers->first() : null;

                $offerDate = date('Y-m-d', strtotime('-' . $offerTemplate['days_ago'] . ' days'));
                $expirationDate = date('Y-m-d', strtotime($offerDate . ' +' . $offerTemplate['expiry_days'] . ' days'));
                $startDate = date('Y-m-d', strtotime($offerDate . ' +' . $offerTemplate['start_days'] . ' days'));
                $responseDate = $offerTemplate['response_days'] ?
                    date('Y-m-d', strtotime('-' . $offerTemplate['response_days'] . ' days')) : null;

                try {
                    Offer::create([
                        'candidate_id' => $candidate->id,
                        'job_id' => $candidate->job_id,
                        'offer_date' => $offerDate,
                        'position' => $candidate->job->title ?? 'Position Title',
                        'department_id' => $department?->id,
                        'salary' => $offerTemplate['salary'],
                        'bonus' => $offerTemplate['bonus'],
                        'equity' => $offerTemplate['equity'],
                        'benefits' => $offerTemplate['benefits'],
                        'start_date' => $startDate,
                        'expiration_date' => $expirationDate,
                        'offer_letter_path' => 'offers/' . $candidate->id . '_offer_letter.pdf',
                        'status' => $offerTemplate['status'],
                        'response_date' => $responseDate,
                        'decline_reason' => $offerTemplate['decline_reason'] ?? null,
                        'created_by' => $company->id,
                        'approved_by' => in_array($offerTemplate['status'], ['Sent', 'Accepted', 'Negotiating', 'Declined', 'Expired']) ? $approver?->id : null,
                    ]);
                } catch (\Exception $e) {
                    $this->command->error('Failed to create offer for candidate: ' . $candidate->first_name . ' ' . $candidate->last_name . ' in company: ' . $company->name);
                    continue;
                }
            }
        }

        $this->command->info('Offer seeder completed successfully!');
    }
}
