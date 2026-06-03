<?php

namespace Tests\Unit;

use App\Models\WeekOff;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class WeekOffTest extends TestCase
{
    /** @test */
    public function it_checks_weekly_off_correctly()
    {
        $weekOff = new WeekOff();
        $weekOff->type = 'weekly';
        $weekOff->settings = ['Sunday', 'Saturday'];

        // 2024-01-07 is a Sunday
        $sunday = Carbon::create(2024, 1, 7);
        $this->assertTrue($weekOff->isDateWeekOff($sunday));

        // 2024-01-08 is a Monday
        $monday = Carbon::create(2024, 1, 8);
        $this->assertFalse($weekOff->isDateWeekOff($monday));
    }

    /** @test */
    public function it_checks_monthly_off_correctly()
    {
        $weekOff = new WeekOff();
        $weekOff->type = 'monthly';
        // 2nd and 4th Saturday off, All Sundays off
        // Note: My implementation currently takes 'settings' as specific days for specific weeks.
        // e.g. Week 1: [Sunday], Week 2: [Saturday, Sunday], etc.

        // Let's verify the implementation intent.
        // If the user wants "Every Sunday", they have to add Sunday to Week 1, 2, 3, 4, 5 in monthly mode.
        // Or users usually toggle between "Weekly" (fixed days) and "Monthly" (variable).

        $weekOff->settings = [
            '1' => ['Sunday'],
            '2' => ['Saturday', 'Sunday'],
            '3' => ['Sunday'],
            '4' => ['Saturday', 'Sunday'],
            '5' => ['Sunday']
        ];

        // Jan 2024
        // 1st Week: Jan 1-7. Jan 6 (Sat), Jan 7 (Sun)
        // 1st Saturday: Jan 6. Should be FALSE (Working)
        $sat1 = Carbon::create(2024, 1, 6);
        // Jan 6 is 1st Saturday. 
        // My Logic: ceil(6 / 7) = 1. Week 1.
        // Week 1 settings: ['Sunday']. Saturday is NOT in Week 1 settings.
        $this->assertFalse($weekOff->isDateWeekOff($sat1));

        // 1st Sunday: Jan 7.
        // ceil(7 / 7) = 1. Week 1. 
        // Week 1 settings: ['Sunday']. TRUE.
        $sun1 = Carbon::create(2024, 1, 7);
        $this->assertTrue($weekOff->isDateWeekOff($sun1));

        // 2nd Week: Jan 8-14.
        // 2nd Saturday: Jan 13.
        // ceil(13 / 7) = 2. Week 2.
        // Week 2 settings: ['Saturday', 'Sunday']. TRUE.
        $sat2 = Carbon::create(2024, 1, 13);
        $this->assertTrue($weekOff->isDateWeekOff($sat2));

        // 3rd Saturday: Jan 20.
        // ceil(20 / 7) = 3. 
        // Week 3 settings: ['Sunday']. FALSE.
        $sat3 = Carbon::create(2024, 1, 20);
        $this->assertFalse($weekOff->isDateWeekOff($sat3));
    }
}
