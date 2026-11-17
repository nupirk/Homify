<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestNotificationTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-notification-template';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Testing NotificationTemplate creation...');

            $data = [
                'name' => 'Test Template ' . now()->timestamp,
                'type' => 'custom',
                'title_template' => 'Test Title',
                'message_template' => 'Test Message',
                'variables' => [],
                'is_active' => true,
                'created_by' => 1
            ];

            $this->info('Data to insert: ' . json_encode($data));

            $template = \App\Models\NotificationTemplate::create($data);

            $this->info('Template created successfully! ID: ' . $template->template_id);

            // Clean up
            $template->delete();
            $this->info('Test template deleted.');
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
