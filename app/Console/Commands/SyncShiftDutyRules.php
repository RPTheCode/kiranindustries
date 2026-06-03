<?php

namespace App\Console\Commands;

use App\Services\ShiftDutyRuleService;
use Illuminate\Console\Command;

class SyncShiftDutyRules extends Command
{
    protected $signature = 'shifts:sync-duty-rules {--no-create-slots : Only update duty rules for existing slots}';

    protected $description = 'Rebuild shift duty rules from slot timings (50% half day, 75% full day)';

    public function handle(): int
    {
        $createMissingSlots = !$this->option('no-create-slots');

        $this->info('Syncing shift slots and duty rules...');

        $stats = ShiftDutyRuleService::syncAll($createMissingSlots);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Slots synced', $stats['slots_synced']],
                ['Slots created', $stats['slots_created']],
                ['Shifts skipped', $stats['shifts_skipped']],
            ]
        );

        $this->info('Shift duty rules synced successfully.');

        return self::SUCCESS;
    }
}
