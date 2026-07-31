<?php

use App\Models\Download;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Team;
use App\Support\CurrentTeam;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('marketplace.releases_disk'));
});

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

/**
 * @return array{0: License, 1: ProductVersion}
 */
function apiLicense(array $state = [], array $versionState = []): array
{
    $product = Product::factory()->create(['slug' => 'aurora-theme']);
    $version = ProductVersion::factory()->for($product)->create(array_merge([
        'version' => '1.2.0',
        'file_path' => 'releases/aurora-1.2.0.zip',
        'released_at' => now()->subDay(),
    ], $versionState));

    Storage::disk(config('marketplace.releases_disk'))->put($version->file_path, 'zip-bytes');

    $license = License::factory()->create(array_merge([
        'team_id' => Team::factory(),
        'product_id' => $product->id,
        'key' => 'AAAA-BBBB-CCCC-DDDD',
    ], $state));

    return [$license, $version];
}

test('an install can activate a license', function () {
    [$license] = apiLicense();

    $this->postJson('/api/v1/license/activate', [
        'key' => 'AAAA-BBBB-CCCC-DDDD',
        'instance' => 'example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('activated', true)
        ->assertJsonPath('activations_used', 1)
        ->assertJsonPath('activation_limit', 1);

    expect($license->activations()->where('instance', 'example.com')->exists())->toBeTrue();
});

test('re-activating the same install is idempotent', function () {
    [$license] = apiLicense();

    $payload = ['key' => 'AAAA-BBBB-CCCC-DDDD', 'instance' => 'example.com'];

    $this->postJson('/api/v1/license/activate', $payload)->assertCreated();
    $this->postJson('/api/v1/license/activate', $payload)
        ->assertOk()
        ->assertJsonPath('activated', true);

    expect($license->activations()->count())->toBe(1);
});

test('instance identifiers are normalized so one install takes one seat', function () {
    [$license] = apiLicense();

    $this->postJson('/api/v1/license/activate', [
        'key' => 'AAAA-BBBB-CCCC-DDDD',
        'instance' => 'https://Example.com/',
    ])->assertCreated();

    $this->postJson('/api/v1/license/activate', [
        'key' => 'AAAA-BBBB-CCCC-DDDD',
        'instance' => 'example.com',
    ])->assertOk();

    expect($license->activations()->count())->toBe(1)
        ->and($license->activations()->first()->instance)->toBe('example.com');
});

test('the license key is matched case-insensitively', function () {
    apiLicense();

    $this->postJson('/api/v1/license/activate', [
        'key' => 'aaaa-bbbb-cccc-dddd',
        'instance' => 'example.com',
    ])->assertCreated();
});

test('activation is refused once the limit is reached', function () {
    [$license] = apiLicense();
    LicenseActivation::factory()->for($license)->create(['instance' => 'first.com']);

    $this->postJson('/api/v1/license/activate', [
        'key' => 'AAAA-BBBB-CCCC-DDDD',
        'instance' => 'second.com',
    ])
        ->assertStatus(422)
        ->assertJsonPath('activated', false);

    expect($license->activations()->count())->toBe(1);
});

test('a revoked license cannot activate', function () {
    apiLicense(['status' => 'revoked']);

    $this->postJson('/api/v1/license/activate', [
        'key' => 'AAAA-BBBB-CCCC-DDDD',
        'instance' => 'example.com',
    ])
        ->assertForbidden()
        ->assertJsonPath('activated', false);
});

test('an unknown key is refused without leaking anything', function () {
    $this->postJson('/api/v1/license/activate', [
        'key' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ',
        'instance' => 'example.com',
    ])
        ->assertNotFound()
        ->assertJsonPath('valid', false);
});

test('activation requires a key and an instance', function () {
    $this->postJson('/api/v1/license/activate', [])->assertStatus(422);
    $this->postJson('/api/v1/license/activate', ['key' => 'AAAA-BBBB-CCCC-DDDD'])->assertStatus(422);
});

test('an install can deactivate itself and free the seat', function () {
    [$license] = apiLicense();
    LicenseActivation::factory()->for($license)->create(['instance' => 'example.com']);

    $this->postJson('/api/v1/license/deactivate', [
        'key' => 'AAAA-BBBB-CCCC-DDDD',
        'instance' => 'example.com',
    ])
        ->assertOk()
        ->assertJsonPath('deactivated', true)
        ->assertJsonPath('activations_used', 0);

    expect($license->activations()->count())->toBe(0);
});

test('check reports license state and the latest version', function () {
    [$license] = apiLicense();
    LicenseActivation::factory()->for($license)->create(['instance' => 'example.com']);

    $this->getJson('/api/v1/license/check?key=AAAA-BBBB-CCCC-DDDD&instance=example.com')
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('product.slug', 'aurora-theme')
        ->assertJsonPath('has_updates_access', true)
        ->assertJsonPath('activated_here', true)
        ->assertJsonPath('activations_used', 1)
        ->assertJsonPath('latest_version', '1.2.0');
});

test('check reports a revoked license as invalid', function () {
    apiLicense(['status' => 'revoked']);

    $this->getJson('/api/v1/license/check?key=AAAA-BBBB-CCCC-DDDD')
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('status', 'revoked');
});

test('latest-version offers a download url when the license may take it', function () {
    apiLicense();

    $this->getJson('/api/v1/license/latest-version?key=AAAA-BBBB-CCCC-DDDD')
        ->assertOk()
        ->assertJsonPath('version', '1.2.0')
        ->assertJsonPath('downloadable', true)
        ->assertJsonPath('message', null)
        ->assertJsonStructure(['download_url', 'changelog', 'released_at', 'size']);
});

test('latest-version withholds the url once updates have lapsed', function () {
    apiLicense(['expires_at' => now()->subMonth()], ['released_at' => now()->subDay()]);

    $this->getJson('/api/v1/license/latest-version?key=AAAA-BBBB-CCCC-DDDD')
        ->assertOk()
        ->assertJsonPath('downloadable', false)
        ->assertJsonPath('download_url', null);
});

test('an auto-updater can download the release and it is audited', function () {
    [$license, $version] = apiLicense();

    $response = $this->get('/api/v1/license/download?key=AAAA-BBBB-CCCC-DDDD');

    $response->assertOk()->assertDownload('aurora-theme-1.2.0.zip');

    expect(Download::acrossTeams()->count())->toBe(1)
        ->and(Download::acrossTeams()->first()->license_id)->toBe($license->id)
        ->and(Download::acrossTeams()->first()->user_id)->toBeNull()
        ->and($version->product->fresh()->downloads_count)->toBe(1);
});

test('a specific version can be requested by number', function () {
    [, $version] = apiLicense();
    $older = ProductVersion::factory()->for($version->product)->create([
        'version' => '1.0.0',
        'file_path' => 'releases/aurora-1.0.0.zip',
        'released_at' => now()->subMonths(2),
    ]);
    Storage::disk(config('marketplace.releases_disk'))->put($older->file_path, 'old-zip');

    $this->get('/api/v1/license/download?key=AAAA-BBBB-CCCC-DDDD&version=1.0.0')
        ->assertOk()
        ->assertDownload('aurora-theme-1.0.0.zip');
});

test('a revoked license cannot download over the api', function () {
    apiLicense(['status' => 'revoked']);

    $this->getJson('/api/v1/license/download?key=AAAA-BBBB-CCCC-DDDD')->assertForbidden();

    expect(Download::acrossTeams()->count())->toBe(0);
});

test('a release published after the updates window is refused over the api', function () {
    apiLicense(['expires_at' => now()->subMonth()], ['released_at' => now()->subDay()]);

    $this->getJson('/api/v1/license/download?key=AAAA-BBBB-CCCC-DDDD')->assertForbidden();

    expect(Download::acrossTeams()->count())->toBe(0);
});

test('an unknown version returns not found', function () {
    apiLicense();

    $this->getJson('/api/v1/license/download?key=AAAA-BBBB-CCCC-DDDD&version=9.9.9')->assertNotFound();
});

test('the license endpoints need no session or token', function () {
    apiLicense();

    // No actingAs anywhere in this file — proves key-only auth works.
    $this->getJson('/api/v1/license/check?key=AAAA-BBBB-CCCC-DDDD')->assertOk();
});
