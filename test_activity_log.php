<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::find(1);

auth()->login($user);

session(['active_branch_id' => 3]); // Some valid branch ID

try {
    $instance = new class {
        use \App\Traits\LogsActivity;
        public function testLog() {
            $this->logActivity('TestModule', 'testAction', 'TestDescription');
        }
    };
    $instance->testLog();
    echo "SUCCESS\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
