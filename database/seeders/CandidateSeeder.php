<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\CandidateSource;
use App\Models\User;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
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
        
        // 20 unique candidates with first 10 having 'Hired' status
        $candidates = [
            ['first_name' => 'Rajesh', 'last_name' => 'Kumar', 'email' => 'rajesh.kumar@email.com', 'phone' => '+91-9876543210', 'current_company' => 'Tech Solutions Pvt Ltd', 'current_position' => 'Senior Developer', 'experience_years' => 6, 'current_salary' => 95000, 'expected_salary' => 120000, 'notice_period' => '2 months', 'skills' => 'JavaScript, React, Node.js, Python, SQL, MongoDB', 'education' => 'B.Tech in Computer Science from IIT Delhi', 'portfolio_url' => 'https://rajeshkumar.dev', 'linkedin_url' => 'https://linkedin.com/in/rajeshkumar', 'status' => 'Hired', 'source' => 'LinkedIn'],
            ['first_name' => 'Priya', 'last_name' => 'Sharma', 'email' => 'priya.sharma@email.com', 'phone' => '+91-9876543211', 'current_company' => 'Digital Marketing Agency', 'current_position' => 'Marketing Manager', 'experience_years' => 4, 'current_salary' => 70000, 'expected_salary' => 85000, 'notice_period' => '1 month', 'skills' => 'Digital Marketing, SEO, SEM, Social Media, Analytics', 'education' => 'MBA in Marketing from Mumbai University', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/priyasharma', 'status' => 'Hired', 'source' => 'Naukri.com'],
            ['first_name' => 'Amit', 'last_name' => 'Patel', 'email' => 'amit.patel@email.com', 'phone' => '+91-9876543212', 'current_company' => 'HR Consultancy Services', 'current_position' => 'HR Specialist', 'experience_years' => 3, 'current_salary' => 60000, 'expected_salary' => 75000, 'notice_period' => '1 month', 'skills' => 'Recruitment, Employee Relations, Performance Management', 'education' => 'Masters in Human Resources from Pune University', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/amitpatel', 'status' => 'Hired', 'source' => 'Employee Referral'],
            ['first_name' => 'Sneha', 'last_name' => 'Reddy', 'email' => 'sneha.reddy@email.com', 'phone' => '+91-9876543213', 'current_company' => 'Financial Services Ltd', 'current_position' => 'Financial Analyst', 'experience_years' => 2, 'current_salary' => 55000, 'expected_salary' => 70000, 'notice_period' => '2 months', 'skills' => 'Financial Analysis, Excel, SQL, Financial Modeling', 'education' => 'CA from ICAI, B.Com from Osmania University', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/snehareddy', 'status' => 'Hired', 'source' => 'Company Website'],
            ['first_name' => 'Vikram', 'last_name' => 'Singh', 'email' => 'vikram.singh@email.com', 'phone' => '+91-9876543214', 'current_company' => 'Operations Excellence Corp', 'current_position' => 'Operations Executive', 'experience_years' => 5, 'current_salary' => 80000, 'expected_salary' => 100000, 'notice_period' => '3 months', 'skills' => 'Operations Management, Process Improvement, Team Leadership', 'education' => 'MBA in Operations from Bangalore University', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/vikramsingh', 'status' => 'Hired', 'source' => 'Indeed'],
            ['first_name' => 'Kavya', 'last_name' => 'Nair', 'email' => 'kavya.nair@email.com', 'phone' => '+91-9876543215', 'current_company' => 'Customer Care Solutions', 'current_position' => 'Customer Support Lead', 'experience_years' => 3, 'current_salary' => 50000, 'expected_salary' => 65000, 'notice_period' => '1 month', 'skills' => 'Customer Service, CRM, Communication, Problem Solving', 'education' => 'BBA from Kerala University', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/kavyanair', 'status' => 'Hired', 'source' => 'Recruitment Agency'],
            ['first_name' => 'Arjun', 'last_name' => 'Gupta', 'email' => 'arjun.gupta@email.com', 'phone' => '+91-9876543216', 'current_company' => null, 'current_position' => 'Fresh Graduate', 'experience_years' => 0, 'current_salary' => null, 'expected_salary' => 35000, 'notice_period' => 'Immediate', 'skills' => 'Java, Python, Web Development, Database Management', 'education' => 'B.Tech in Computer Science from NIT Warangal', 'portfolio_url' => 'https://arjungupta.github.io', 'linkedin_url' => 'https://linkedin.com/in/arjungupta', 'status' => 'Hired', 'source' => 'Campus Recruitment'],
            ['first_name' => 'Meera', 'last_name' => 'Joshi', 'email' => 'meera.joshi@email.com', 'phone' => '+91-9876543217', 'current_company' => 'Design Studio', 'current_position' => 'UI Designer', 'experience_years' => 2, 'current_salary' => 45000, 'expected_salary' => 60000, 'notice_period' => '1 month', 'skills' => 'UI/UX Design, Figma, Adobe Creative Suite, Prototyping', 'education' => 'Bachelor of Design from NIFT Delhi', 'portfolio_url' => 'https://meerajoshi.design', 'linkedin_url' => 'https://linkedin.com/in/meerajoshi', 'status' => 'Hired', 'source' => 'Walk-in Interview'],
            ['first_name' => 'Rohit', 'last_name' => 'Agarwal', 'email' => 'rohit.agarwal@email.com', 'phone' => '+91-9876543218', 'current_company' => 'Software Solutions Inc', 'current_position' => 'Backend Developer', 'experience_years' => 4, 'current_salary' => 85000, 'expected_salary' => 105000, 'notice_period' => '2 months', 'skills' => 'Java, Spring Boot, Microservices, AWS', 'education' => 'B.Tech in Computer Science from VIT', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/rohitagarwal', 'status' => 'Hired', 'source' => 'LinkedIn'],
            ['first_name' => 'Anita', 'last_name' => 'Desai', 'email' => 'anita.desai@email.com', 'phone' => '+91-9876543219', 'current_company' => 'Marketing Pro', 'current_position' => 'Content Manager', 'experience_years' => 3, 'current_salary' => 65000, 'expected_salary' => 80000, 'notice_period' => '1 month', 'skills' => 'Content Marketing, Copywriting, Social Media', 'education' => 'Masters in Mass Communication', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/anitadesai', 'status' => 'Hired', 'source' => 'Naukri.com'],
            ['first_name' => 'Karan', 'last_name' => 'Mehta', 'email' => 'karan.mehta@email.com', 'phone' => '+91-9876543220', 'current_company' => 'Finance Corp', 'current_position' => 'Senior Analyst', 'experience_years' => 5, 'current_salary' => 90000, 'expected_salary' => 110000, 'notice_period' => '3 months', 'skills' => 'Financial Planning, Risk Analysis, Investment', 'education' => 'MBA in Finance from IIM', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/karanmehta', 'status' => 'Offer', 'source' => 'Company Website'],
            ['first_name' => 'Deepika', 'last_name' => 'Rao', 'email' => 'deepika.rao@email.com', 'phone' => '+91-9876543221', 'current_company' => 'HR Solutions', 'current_position' => 'Talent Manager', 'experience_years' => 4, 'current_salary' => 75000, 'expected_salary' => 95000, 'notice_period' => '2 months', 'skills' => 'Talent Acquisition, HR Analytics, Employee Engagement', 'education' => 'Masters in HR Management', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/deepikarao', 'status' => 'Screening', 'source' => 'Employee Referral'],
            ['first_name' => 'Sanjay', 'last_name' => 'Verma', 'email' => 'sanjay.verma@email.com', 'phone' => '+91-9876543222', 'current_company' => 'Operations Hub', 'current_position' => 'Process Manager', 'experience_years' => 6, 'current_salary' => 100000, 'expected_salary' => 125000, 'notice_period' => '3 months', 'skills' => 'Process Optimization, Six Sigma, Lean Management', 'education' => 'MBA in Operations Management', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/sanjayverma', 'status' => 'New', 'source' => 'Indeed'],
            ['first_name' => 'Pooja', 'last_name' => 'Iyer', 'email' => 'pooja.iyer@email.com', 'phone' => '+91-9876543223', 'current_company' => 'Customer First', 'current_position' => 'Service Manager', 'experience_years' => 4, 'current_salary' => 70000, 'expected_salary' => 85000, 'notice_period' => '2 months', 'skills' => 'Customer Relations, Service Excellence, Team Management', 'education' => 'MBA in Service Management', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/poojaiyer', 'status' => 'Interview', 'source' => 'Recruitment Agency'],
            ['first_name' => 'Rahul', 'last_name' => 'Jain', 'email' => 'rahul.jain@email.com', 'phone' => '+91-9876543224', 'current_company' => null, 'current_position' => 'Recent Graduate', 'experience_years' => 0, 'current_salary' => null, 'expected_salary' => 40000, 'notice_period' => 'Immediate', 'skills' => 'Python, Machine Learning, Data Analysis', 'education' => 'B.Tech in Computer Science from BITS', 'portfolio_url' => 'https://rahuljain.dev', 'linkedin_url' => 'https://linkedin.com/in/rahuljain', 'status' => 'New', 'source' => 'Campus Recruitment'],
            ['first_name' => 'Neha', 'last_name' => 'Kapoor', 'email' => 'neha.kapoor@email.com', 'phone' => '+91-9876543225', 'current_company' => 'Creative Agency', 'current_position' => 'Graphic Designer', 'experience_years' => 3, 'current_salary' => 55000, 'expected_salary' => 70000, 'notice_period' => '1 month', 'skills' => 'Graphic Design, Branding, Adobe Creative Suite', 'education' => 'Bachelor of Fine Arts', 'portfolio_url' => 'https://nehakapoor.design', 'linkedin_url' => 'https://linkedin.com/in/nehakapoor', 'status' => 'Rejected', 'source' => 'Walk-in Interview'],
            ['first_name' => 'Arun', 'last_name' => 'Krishnan', 'email' => 'arun.krishnan@email.com', 'phone' => '+91-9876543226', 'current_company' => 'Tech Innovations', 'current_position' => 'DevOps Engineer', 'experience_years' => 5, 'current_salary' => 95000, 'expected_salary' => 115000, 'notice_period' => '2 months', 'skills' => 'Docker, Kubernetes, CI/CD, AWS, Jenkins', 'education' => 'B.Tech in Information Technology', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/arunkrishnan', 'status' => 'Screening', 'source' => 'LinkedIn'],
            ['first_name' => 'Swati', 'last_name' => 'Bansal', 'email' => 'swati.bansal@email.com', 'phone' => '+91-9876543227', 'current_company' => 'Digital Solutions', 'current_position' => 'Product Manager', 'experience_years' => 4, 'current_salary' => 110000, 'expected_salary' => 135000, 'notice_period' => '3 months', 'skills' => 'Product Strategy, Agile, User Research, Analytics', 'education' => 'MBA in Product Management', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/swatibansal', 'status' => 'Offer', 'source' => 'Naukri.com'],
            ['first_name' => 'Manish', 'last_name' => 'Gupta', 'email' => 'manish.gupta@email.com', 'phone' => '+91-9876543228', 'current_company' => 'Data Analytics Co', 'current_position' => 'Data Scientist', 'experience_years' => 3, 'current_salary' => 85000, 'expected_salary' => 105000, 'notice_period' => '2 months', 'skills' => 'Machine Learning, Python, R, SQL, Statistics', 'education' => 'Masters in Data Science', 'portfolio_url' => 'https://manishgupta.ml', 'linkedin_url' => 'https://linkedin.com/in/manishgupta', 'status' => 'Interview', 'source' => 'Company Website'],
            ['first_name' => 'Ravi', 'last_name' => 'Tiwari', 'email' => 'ravi.tiwari@email.com', 'phone' => '+91-9876543229', 'current_company' => 'Consulting Firm', 'current_position' => 'Business Analyst', 'experience_years' => 3, 'current_salary' => 75000, 'expected_salary' => 90000, 'notice_period' => '2 months', 'skills' => 'Business Analysis, Requirements Gathering, Process Mapping', 'education' => 'MBA from ISB Hyderabad', 'portfolio_url' => null, 'linkedin_url' => 'https://linkedin.com/in/ravitiwari', 'status' => 'New', 'source' => 'Indeed']
        ];
        
        foreach ($companies as $company) {
            // Get job postings for this company
            $jobPostings = JobPosting::where('created_by', $company->id)->get();
            
            if ($jobPostings->isEmpty()) {
                $this->command->warn('No job postings found for company: ' . $company->name . '. Please run JobPostingSeeder first.');
                continue;
            }
            
            // Get candidate sources for this company
            $candidateSources = CandidateSource::where('created_by', $company->id)->get();
            
            if ($candidateSources->isEmpty()) {
                $this->command->warn('No candidate sources found for company: ' . $company->name . '. Please run CandidateSourceSeeder first.');
                continue;
            }
            
            // Get employees for referrals
            $employees = User::where('type', 'employee')->where('created_by', $company->id)->get();
            
            // Create 20 candidates
            foreach ($candidates as $index => $candidateData) {
                // Cycle through job postings
                $jobPosting = $jobPostings[$index % $jobPostings->count()];
                
                // Find matching source
                $source = $candidateSources->where('name', $candidateData['source'])->first();
                if (!$source) $source = $candidateSources->first();
                
                // Set referral employee for employee referral source
                $referralEmployee = null;
                if ($candidateData['source'] === 'Employee Referral' && $employees->isNotEmpty()) {
                    $referralEmployee = $employees->first();
                }
                
                // Check if candidate already exists for this company
                if (Candidate::where('email', $candidateData['email'])->where('created_by', $company->id)->exists()) {
                    continue;
                }
                
                $applicationDate = date('Y-m-d', strtotime('-' . ($index + 1) . ' days'));
                
                try {
                    Candidate::create([
                        'job_id' => $jobPosting->id,
                        'source_id' => $source->id,
                        'first_name' => $candidateData['first_name'],
                        'last_name' => $candidateData['last_name'],
                        'email' => $candidateData['email'],
                        'phone' => $candidateData['phone'],
                        'current_company' => $candidateData['current_company'],
                        'current_position' => $candidateData['current_position'],
                        'experience_years' => $candidateData['experience_years'],
                        'current_salary' => $candidateData['current_salary'],
                        'expected_salary' => $candidateData['expected_salary'],
                        'notice_period' => $candidateData['notice_period'],
                        'resume_path' => 'resumes/' . strtolower($candidateData['first_name']) . '_' . strtolower($candidateData['last_name']) . '_resume.pdf',
                        'cover_letter_path' => null,
                        'skills' => $candidateData['skills'],
                        'education' => $candidateData['education'],
                        'portfolio_url' => $candidateData['portfolio_url'],
                        'linkedin_url' => $candidateData['linkedin_url'],
                        'referral_employee_id' => $referralEmployee?->id,
                        'status' => $candidateData['status'],
                        'application_date' => $applicationDate,
                        'created_by' => $company->id,
                    ]);
                    
                } catch (\Exception $e) {
                    $this->command->error('Failed to create candidate: ' . $candidateData['first_name'] . ' ' . $candidateData['last_name'] . ' for company: ' . $company->name);
                    continue;
                }
            }
        }
        
        $this->command->info('Candidate seeder completed successfully!');
    }
}