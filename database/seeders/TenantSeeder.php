<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Event;
use App\Models\BidItemCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo tenant
        $tenant = Tenant::create([
            'name' => 'Demo Nonprofit Organization',
            'slug' => 'demo-nonprofit',
            'active' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
            'config' => [
                'theme_color' => '#3B82F6',
                'logo_url' => null,
                'contact_email' => 'contact@demo-nonprofit.org',
            ],
        ]);

        // Create admin user
        $adminUser = TenantUser::create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Admin',
            'email' => 'admin@demo-nonprofit.org',
            'password' => Hash::make('password'),
            'role' => 'org_admin',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        // Create event staff user
        $staffUser = TenantUser::create([
            'tenant_id' => $tenant->id,
            'name' => 'Event Staff',
            'email' => 'staff@demo-nonprofit.org',
            'password' => Hash::make('password'),
            'role' => 'event_staff',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        // Create demo event
        $event = Event::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Charity Gala 2024',
            'slug' => 'annual-charity-gala-2024',
            'description' => 'Join us for our annual charity gala featuring silent auctions, live entertainment, and dinner.',
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(30)->addHours(6),
            'location' => 'Grand Ballroom, Downtown Convention Center',
            'status' => 'draft',
            'transaction_fee_percentage' => 2.50,
            'fixed_transaction_fee' => 0.30,
        ]);

        // Create bid item categories
        $categories = [
            [
                'name' => 'Artwork & Collectibles',
                'slug' => 'artwork-collectibles',
                'description' => 'Original artwork, sculptures, and collectible items',
                'color' => '#8B5CF6',
            ],
            [
                'name' => 'Experiences & Travel',
                'slug' => 'experiences-travel',
                'description' => 'Vacation packages, dining experiences, and unique adventures',
                'color' => '#10B981',
            ],
            [
                'name' => 'Sports & Recreation',
                'slug' => 'sports-recreation',
                'description' => 'Sports memorabilia, tickets, and recreational activities',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Services & Professional',
                'slug' => 'services-professional',
                'description' => 'Professional services, consultations, and business offerings',
                'color' => '#EF4444',
            ],
            [
                'name' => 'Home & Garden',
                'slug' => 'home-garden',
                'description' => 'Home decor, furniture, and garden items',
                'color' => '#06B6D4',
            ],
        ];

        foreach ($categories as $categoryData) {
            BidItemCategory::create(array_merge($categoryData, [
                'tenant_id' => $tenant->id,
                'active' => true,
            ]));
        }

        $this->command->info('Demo tenant created successfully!');
        $this->command->info('Tenant: ' . $tenant->name);
        $this->command->info('Slug: ' . $tenant->slug);
        $this->command->info('Admin Email: ' . $adminUser->email);
        $this->command->info('Staff Email: ' . $staffUser->email);
        $this->command->info('Password: password');
        $this->command->info('Event: ' . $event->name);
    }
}
