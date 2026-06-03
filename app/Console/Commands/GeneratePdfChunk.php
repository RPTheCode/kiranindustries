<?php

namespace App\Console\Commands;

use App\Services\ReportChunkGenerator;
use Illuminate\Console\Command;

class GeneratePdfChunk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:generate-chunk {downloadId} {index} {paramsBase64}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates a PDF chunk in a separate process to prevent memory leaks';

    /**
     * Execute the console command.
     */
    public function handle(ReportChunkGenerator $generator)
    {
        $downloadId = (int) $this->argument('downloadId');
        $index = (int) $this->argument('index');
        $paramsStr = base64_decode($this->argument('paramsBase64'));
        $subParams = json_decode($paramsStr, true);

        if (! $subParams) {
            $this->error('Invalid parameters');

            return 1;
        }

        try {
            $path = $generator->generate($downloadId, $index, $subParams);

            if ($path === null) {
                $this->info("Chunk {$index} is empty. Skipping file creation.");

                return 0;
            }

            $this->info("Chunk {$index} generated successfully.");

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage() . ' - ' . $e->getLine());
            \Log::error('Chunk Generation Error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());

            return 1;
        }
    }
}
