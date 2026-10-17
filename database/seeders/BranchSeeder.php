<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::firstOrCreate(
            ['code' => 'RSH'],
            [
                'name' => 'Rishikesh Campus (Ashram)',
                'address_line_1' => 'Tapovan, Badrinath Road',
                'city' => 'Rishikesh',
                'state' => 'Uttarakhand',
                'country' => 'IN',
                'postal_code' => '249192',
                'phone' => '+91-135-2430000',
                'email' => 'rishikesh@pramana-institute.org',
                'timezone' => 'Asia/Kolkata',
                'is_active' => true,
            ]
        );

        Branch::firstOrCreate(
            ['code' => 'DEL'],
            [
                'name' => 'New Delhi Learning Center',
                'address_line_1' => 'Hauz Khas Enclave',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'country' => 'IN',
                'postal_code' => '110016',
                'phone' => '+91-11-26500000',
                'email' => 'delhi@pramana-institute.org',
                'timezone' => 'Asia/Kolkata',
                'is_active' => true,
            ]
        );

        Branch::firstOrCreate(
            ['code' => 'BLR'],
            [
                'name' => 'Bengaluru Center for Indic Studies',
                'address_line_1' => 'Indiranagar 100ft Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'country' => 'IN',
                'postal_code' => '560038',
                'phone' => '+91-80-25200000',
                'email' => 'bengaluru@pramana-institute.org',
                'timezone' => 'Asia/Kolkata',
                'is_active' => true,
            ]
        );
    }
}
