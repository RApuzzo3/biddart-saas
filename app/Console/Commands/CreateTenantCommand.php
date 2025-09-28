<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:create 
                            {name : The name of the tenant organization}
                            {--slug= : Custom slug for the tenant}
                            {--domain= : Custom domain for the tenant}
                            {--admin-name= : Admin user name}
                            {--admin-email= : Admin user email}
                            {--admin-password= : Admin user password}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new tenant with admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);
        $domain = $this->option('domain');

        // Check if tenant already exists
        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("Tenant with slug '{$slug}' already exists!");
            return 1;
        }

        if ($domain && Tenant::where('domain', $domain)->exists()) {
            $this->error("Tenant with domain '{$domain}' already exists!");
            return 1;
        }

        // Create tenant
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'domain' => $domain,
            'active' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->info("Tenant '{$name}' created successfully!");
        $this->info("Slug: {$tenant->slug}");
        $this->info("ID: {$tenant->id}");

        if ($domain) {
            $this->info("Domain: {$domain}");
        }

        // Create admin user if details provided
        $adminName = $this->option('admin-name');
        $adminEmail = $this->option('admin-email');
        $adminPassword = $this->option('admin-password');

        if (!$adminName || !$adminEmail || !$adminPassword) {
            if ($this->confirm('Would you like to create an admin user for this tenant?')) {
                $adminName = $this->ask('Admin user name');
                $adminEmail = $this->ask('Admin user email');
                $adminPassword = $this->secret('Admin user password');
            }
        }

        if ($adminName && $adminEmail && $adminPassword) {
            $adminUser = TenantUser::create([
                'tenant_id' => $tenant->id,
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role' => 'org_admin',
                'active' => true,
                'email_verified_at' => now(),
            ]);

            $this->info("Admin user created successfully!");
            $this->info("Email: {$adminUser->email}");
            $this->info("Role: {$adminUser->role}");
        }

        // Display access information
        $this->newLine();
        $this->info('=== Access Information ===');
        
        if ($domain) {
            $this->info("Tenant URL: https://{$domain}");
        } else {
            $baseUrl = config('app.url');
            $parsedUrl = parse_url($baseUrl);
            $subdomainUrl = $parsedUrl['scheme'] . '://' . $slug . '.' . $parsedUrl['host'];
            $this->info("Tenant URL: {$subdomainUrl}");
        }

        $this->info("Trial expires: {$tenant->trial_ends_at->format('Y-m-d H:i:s')}");

        return 0;
    }
}
