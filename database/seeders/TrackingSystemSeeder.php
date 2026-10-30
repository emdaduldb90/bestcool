<?php

namespace Database\Seeders;

use App\Models\TrackingDestination;
use App\Models\TrackingEventDefinition;
use App\Models\TrackingEventRoute;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class TrackingSystemSeeder extends Seeder
{
    public function run(): void
    {
        $viewPermission = Permission::query()->updateOrCreate(['slug' => 'tracking.view'], ['name' => 'View tracking analytics']);
        $managePermission = Permission::query()->updateOrCreate(['slug' => 'tracking.manage'], ['name' => 'Manage tracking and AI settings']);
        Role::query()->where('slug', 'super-admin')->first()?->permissions()->syncWithoutDetaching([$viewPermission->id, $managePermission->id]);
        Role::query()->where('slug', 'manager')->first()?->permissions()->syncWithoutDetaching([$viewPermission->id]);

        $destinations = [
            'meta' => ['name' => 'Meta Conversions API', 'settings' => ['api_version' => '', 'test_event_code' => '']],
            'tiktok' => ['name' => 'TikTok Events API', 'settings' => ['test_event_code' => '']],
            'ga4' => ['name' => 'Google Analytics 4', 'settings' => ['region' => 'global']],
            'openai' => ['name' => 'OpenAI Business Reports', 'settings' => ['model' => 'gpt-6-astra', 'language' => 'bn', 'monthly_token_budget' => 50000, 'daily_report_limit' => 3]],
        ];

        foreach ($destinations as $code => $attributes) {
            TrackingDestination::query()->firstOrCreate(['code' => $code], $attributes + [
                'enabled' => false, 'browser_enabled' => false, 'server_enabled' => false,
            ]);
        }

        foreach ([
            'PageView' => ['Page view', 'analytics'],
            'ViewContent' => ['Product or content view', 'analytics'],
            'AddToCart' => ['Successful cart add', 'analytics'],
            'InitiateCheckout' => ['Checkout page loaded', 'analytics'],
            'Purchase' => ['Order placed (COD)', 'analytics'],
            'LinkClick' => ['Link click', 'analytics'],
            'PhoneClick' => ['Phone link click', 'analytics'],
            'EmailClick' => ['Email link click', 'analytics'],
            'Search' => ['Executed product search', 'analytics'],
        ] as $name => [$label, $consent]) {
            $definition = TrackingEventDefinition::query()->firstOrCreate(
                ['name' => $name],
                ['label' => $label, 'enabled' => true, 'consent_category' => $consent],
            );

            foreach (['meta', 'tiktok', 'ga4'] as $code) {
                $destination = TrackingDestination::query()->where('code', $code)->first();
                foreach (['browser', 'server'] as $channel) {
                    $enabled = match ([$code, $name, $channel]) {
                        ['meta', 'Purchase', 'browser'], ['tiktok', 'Purchase', 'browser'], ['ga4', 'Purchase', 'browser'] => false,
                        ['ga4', 'PageView', 'server'], ['ga4', 'ViewContent', 'server'], ['ga4', 'AddToCart', 'server'], ['ga4', 'InitiateCheckout', 'server'] => false,
                        ['ga4', 'LinkClick', 'server'], ['ga4', 'PhoneClick', 'server'], ['ga4', 'EmailClick', 'server'], ['ga4', 'Search', 'server'] => false,
                        ['meta', 'PageView', 'server'], ['meta', 'ViewContent', 'server'], ['meta', 'Search', 'server'] => false,
                        ['tiktok', 'PageView', 'server'], ['tiktok', 'ViewContent', 'server'], ['tiktok', 'Search', 'server'] => false,
                        default => true,
                    };
                    TrackingEventRoute::query()->firstOrCreate(
                        ['event_definition_id' => $definition->id, 'destination_id' => $destination->id, 'channel' => $channel],
                        ['consent_category' => $code === 'ga4' ? 'analytics' : 'advertising', 'enabled' => $enabled],
                    );
                }
            }
        }
    }
}
