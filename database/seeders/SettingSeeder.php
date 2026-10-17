<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings\SettingsService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(SettingsService $settings): void
    {
        $defaults = [
            [
                'key' => 'institute_name',
                'value' => 'Pramana Institute of Yoga & Indic Sciences',
                'type' => 'string',
                'group' => 'general',
                'is_public' => true,
            ],
            [
                'key' => 'institute_logo',
                'value' => '/images/pramana-logo.svg',
                'type' => 'string',
                'group' => 'general',
                'is_public' => true,
            ],
            [
                'key' => 'default_currency',
                'value' => 'INR',
                'type' => 'string',
                'group' => 'finance',
                'is_public' => true,
            ],
            [
                'key' => 'default_timezone',
                'value' => 'Asia/Kolkata',
                'type' => 'string',
                'group' => 'general',
                'is_public' => true,
            ],
            [
                'key' => 'contact_email',
                'value' => 'admissions@pramana-institute.org',
                'type' => 'string',
                'group' => 'contact',
                'is_public' => true,
            ],
            [
                'key' => 'contact_phone',
                'value' => '+91-135-2430000',
                'type' => 'string',
                'group' => 'contact',
                'is_public' => true,
            ],
            [
                'key' => 'contact_address',
                'value' => 'Tapovan, Badrinath Road, Rishikesh, Uttarakhand 249192, India',
                'type' => 'string',
                'group' => 'contact',
                'is_public' => true,
            ],
            [
                'key' => 'gst_number',
                'value' => '05AAAAA0000A1Z5',
                'type' => 'string',
                'group' => 'finance',
                'is_public' => false,
            ],
        ];

        foreach ($defaults as $item) {
            $settings->set(
                key: $item['key'],
                value: $item['value'],
                type: $item['type'],
                group: $item['group'],
                isPublic: $item['is_public'],
            );
        }
    }
}
