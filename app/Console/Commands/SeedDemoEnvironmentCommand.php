<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\PublicSiteSeeder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lms:demo-seed', description: 'Seed full demo environment with realistic Gurukula data')]
class SeedDemoEnvironmentCommand extends Command
{
    protected $signature = 'lms:demo-seed {--fresh : Refresh database before seeding}';

    protected $description = 'Seed comprehensive demo data for all portals (admin, teacher, student, moderator, public site)';

    public function handle(): int
    {
        $this->info('Initializing Pramana Gurukula LMS Demo Seeder...');

        if ($this->option('fresh')) {
            $this->warn('Running migrate:fresh...');
            $this->call('migrate:fresh');
        }

        $this->info('Seeding public catalog, faculty, events, articles, FAQs, and reviews...');
        $this->call(PublicSiteSeeder::class);

        $this->table(
            ['Portal', 'Credentials', 'URL'],
            [
                ['Filament Admin Portal', 'admin@pramana.edu / Secret123!', '/admin/login'],
                ['Teacher / Acharya Portal', 'shivamurti@pramana.edu / Secret123!', '/teacher/dashboard'],
                ['Student Learning Portal', 'aranya.student@pramana.edu / Secret123!', '/student/dashboard'],
                ['Academic Moderator Console', 'moderator@pramana.edu / Secret123!', '/moderator/console'],
                ['Public Course Catalog', 'Anonymous Public Access', '/courses'],
                ['Events & Retreats Calendar', 'Anonymous Public Access', '/events-calendar'],
            ]
        );

        $this->info('Demo environment ready!');

        return self::SUCCESS;
    }
}
