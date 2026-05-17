<?php

namespace Modules\Pharmacy\Console;

use Illuminate\Console\Command;
use Modules\Pharmacy\Models\Drug;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ImportFDANdcDrugData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fda-ndc:import {--fresh}';

    /**
     * The console command description.
     */
    protected $description = 'Import openFDA NDC data into Drug model.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle() {
        $this->info('Starting NDC import...');

        $path = __DIR__.'/../../resources/assets/data/drug-ndc-0001-of-0001.json';

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            $this->info('Download it from: https://download.open.fda.gov/drug/ndc/drug-ndc-0001-of-0001.json.zip');
            $this->info('Then unzip it into storage/app/data/ndc/');
            return 1;
        }

        if ($this->option('fresh')) {
            $this->warn('Truncating drugs table...');
            Drug::truncate();
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        $records = $data['results'] ?? [];
        $total = count($records);

        $this->info("Found {$total} records. Importing...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0;

        foreach ($records as $item) {
            $ndc = $item['product_ndc'] ?? null;

            if (!$ndc) continue;
            if(isset($item['generic_name']) && !empty($item['generic_name'])){
                Drug::updateOrCreate(
                    ['ndc_code' => $ndc],
                    [
                        'source_provider'   => 'openfda',
                        'source_identifier' => $ndc,
                        'ndc_code'          => $ndc,
                        'generic_name'      => $item['generic_name'] ?? null,
                        'brand_name'        => $item['brand_name'] ?? null,
                        'display_name'      => $item['brand_name'] ?? $item['generic_name'] ?? null,
                        'strength_text'     => $item['active_ingredients'][0]['strength'] ?? null,
                        'dosage_form_text'  => $item['dosage_form'] ?? null,
                        'raw_payload'       => $item,
                        'is_cached_external'=> true,
                        'is_active'         => true,
                    ]
                );
            }

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Import completed! {$imported} records imported/updated.");
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }
}
