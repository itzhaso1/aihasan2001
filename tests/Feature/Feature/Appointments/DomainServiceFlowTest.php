<?php

namespace Tests\Feature\Feature\Appointments;

use App\Jobs\RegisterDomainJob;
use App\Models\User;
use App\Models\Website\Website;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use App\Services\Domain\DomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DomainServiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_domains_applies_markup_from_configuration(): void
    {
        config()->set('website.domain_markup_percent', 10);

        $this->app->bind(DomainRegistrarInterface::class, fn () => new class implements DomainRegistrarInterface
        {
            public function checkAvailability(array $domains): array
            {
                return [[
                    'domain' => $domains[0],
                    'available' => true,
                    'is_premium' => true,
                    'registration_price' => 100.0,
                    'renewal_price' => 120.0,
                    'transfer_price' => 80.0,
                    'premium_registration_price' => 100.0,
                    'premium_renewal_price' => 120.0,
                    'premium_transfer_price' => 80.0,
                    'icann_fee' => 0.18,
                    'eap_fee' => 0.0,
                ]];
            }

            public function register(string $domain, int $years, array $contacts, array $options = []): array
            {
                return [];
            }

            public function getInfo(string $domain): array
            {
                return [];
            }

            public function getDomains(array $filters = []): array
            {
                return [];
            }

            public function getDnsRecords(string $domain): array
            {
                return [];
            }

            public function setDnsRecords(string $domain, array $records, ?string $emailType = null): array
            {
                return [];
            }

            public function renew(string $domain, int $years, array $options = []): array
            {
                return [];
            }

            public function getTldPricing(array $tlds, int $years = 1): array
            {
                return [];
            }
        });

        /** @var DomainService $service */
        $service = app(DomainService::class);
        $result = $service->searchDomains('aida-clinic', ['com']);

        $this->assertCount(1, $result);
        $this->assertSame(110.0, $result[0]['registration_price']);
        $this->assertSame(132.0, $result[0]['renewal_price']);
        $this->assertSame(88.0, $result[0]['transfer_price']);
    }

    public function test_purchase_domain_creates_domain_record_and_queues_registration_job(): void
    {
        Bus::fake();
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $this->enableWebsiteFeatures($workspace);

        $website = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Domain Site',
            'slug' => 'domain-site',
            'status' => 'draft',
            'preview_token' => 'tok-domain-site',
            'settings' => [],
            'theme' => [],
            'metadata' => [],
        ]);

        /** @var DomainService $service */
        $service = app(DomainService::class);
        $domain = $service->purchaseDomain(
            website: $website,
            domain: 'domain-site.com',
            years: 1,
            contacts: [
                'registrant' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'address1' => 'Street',
                    'city' => 'Riyadh',
                    'state_province' => 'Riyadh',
                    'postal_code' => '11111',
                    'country' => 'SA',
                    'phone' => '+966.500000000',
                    'email' => 'john@example.test',
                ],
                'admin' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'address1' => 'Street',
                    'city' => 'Riyadh',
                    'state_province' => 'Riyadh',
                    'postal_code' => '11111',
                    'country' => 'SA',
                    'phone' => '+966.500000000',
                    'email' => 'john@example.test',
                ],
                'tech' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'address1' => 'Street',
                    'city' => 'Riyadh',
                    'state_province' => 'Riyadh',
                    'postal_code' => '11111',
                    'country' => 'SA',
                    'phone' => '+966.500000000',
                    'email' => 'john@example.test',
                ],
                'aux_billing' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'address1' => 'Street',
                    'city' => 'Riyadh',
                    'state_province' => 'Riyadh',
                    'postal_code' => '11111',
                    'country' => 'SA',
                    'phone' => '+966.500000000',
                    'email' => 'john@example.test',
                ],
            ],
            actorUserId: $owner->id,
        );

        $this->assertSame('registering', $domain->status);
        $this->assertSame('domain-site.com', $domain->normalized_domain);
        Bus::assertDispatched(RegisterDomainJob::class);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
            'status' => 'active',
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }

    private function enableWebsiteFeatures(Workspace $workspace): void
    {
        foreach (['website_builder', 'custom_domains', 'public_booking', 'appointments'] as $feature) {
            WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'feature_key' => $feature],
                ['workspace_id' => $workspace->id, 'feature_key' => $feature, 'enabled' => true, 'source' => 'manual']
            );
        }
    }
}
