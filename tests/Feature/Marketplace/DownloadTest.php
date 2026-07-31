<?php

use App\Actions\Marketplace\AuthorizeDownload;
use App\Models\Download;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake(config('marketplace.releases_disk'));
});

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

/**
 * A buyer holding an active license for a product with one release,
 * whose file actually exists on the fake disk.
 *
 * @return array{0: User, 1: Team, 2: License, 3: ProductVersion}
 */
function licensedBuyer(array $licenseState = []): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $user->forceFill(['current_team_id' => $team->id])->save();

    $product = Product::factory()->create();
    $version = ProductVersion::factory()->for($product)->create([
        'version' => '1.0.0',
        'file_path' => 'releases/theme-1.0.0.zip',
        'released_at' => now()->subDay(),
    ]);

    Storage::disk(config('marketplace.releases_disk'))->put($version->file_path, 'zip-bytes');

    $license = License::factory()->create(array_merge([
        'team_id' => $team->id,
        'product_id' => $product->id,
    ], $licenseState));

    return [$user, $team, $license, $version];
}

test('a licensed buyer is handed a signed link and can redeem it', function () {
    [$user, , $license, $version] = licensedBuyer();

    $response = $this->actingAs($user)->post(route('downloads.create', [$license, $version]));

    $response->assertRedirect();
    $signedUrl = $response->headers->get('Location');

    expect($signedUrl)->toContain('signature=');

    $this->actingAs($user)->get($signedUrl)
        ->assertOk()
        ->assertDownload($version->product->slug.'-1.0.0.zip');
});

test('redeeming a link records the download and bumps the product counter', function () {
    [$user, $team, $license, $version] = licensedBuyer();

    $url = URL::temporarySignedRoute('downloads.show', now()->addMinutes(15), [
        'license' => $license->id,
        'version' => $version->id,
    ]);

    $this->actingAs($user)->get($url)->assertOk();

    app(CurrentTeam::class)->set($team);

    expect(Download::query()->count())->toBe(1)
        ->and(Download::query()->first()->license_id)->toBe($license->id)
        ->and(Download::query()->first()->user_id)->toBe($user->id)
        ->and($version->product->fresh()->downloads_count)->toBe(1);
});

test('an unsigned download link is rejected', function () {
    [$user, , $license, $version] = licensedBuyer();

    $this->actingAs($user)
        ->get(route('downloads.show', [$license, $version]))
        ->assertForbidden();
});

test('an expired signed link is rejected', function () {
    [$user, , $license, $version] = licensedBuyer();

    $url = URL::temporarySignedRoute('downloads.show', now()->addMinutes(15), [
        'license' => $license->id,
        'version' => $version->id,
    ]);

    $this->travel(20)->minutes();

    $this->actingAs($user)->get($url)->assertForbidden();

    expect(Download::withoutGlobalScope('team')->count())->toBe(0);
});

test('guests cannot download anything', function () {
    [, , $license, $version] = licensedBuyer();

    $this->post(route('downloads.create', [$license, $version]))
        ->assertRedirect(route('login', absolute: false));
});

test('another team cannot download using a license it does not own', function () {
    [, , $license, $version] = licensedBuyer();

    $stranger = User::factory()->create();
    $strangerTeam = Team::factory()->create(['owner_id' => $stranger->id]);
    $stranger->forceFill(['current_team_id' => $strangerTeam->id])->save();

    // Refused by the controller's ownership check.
    $this->actingAs($stranger)
        ->post(route('downloads.create', [$license, $version]))
        ->assertForbidden();

    // Even a validly signed URL must not work for the wrong team. Here
    // the team-scoped binding refuses first, so this one is a 404.
    $url = URL::temporarySignedRoute('downloads.show', now()->addMinutes(15), [
        'license' => $license->id,
        'version' => $version->id,
    ]);

    $this->actingAs($stranger)->get($url)->assertNotFound();

    expect(Download::withoutGlobalScope('team')->count())->toBe(0);
});

test('a revoked license cannot download', function () {
    [$user, , $license, $version] = licensedBuyer(['status' => 'revoked']);

    $this->actingAs($user)
        ->post(route('downloads.create', [$license, $version]))
        ->assertRedirect();

    $url = URL::temporarySignedRoute('downloads.show', now()->addMinutes(15), [
        'license' => $license->id,
        'version' => $version->id,
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('purchases.index', absolute: false));

    expect(Download::withoutGlobalScope('team')->count())->toBe(0);
});

test('a version from another product cannot be downloaded with this license', function () {
    [$user, , $license] = licensedBuyer();

    $otherVersion = ProductVersion::factory()->create(['file_path' => 'releases/other.zip']);
    Storage::disk(config('marketplace.releases_disk'))->put($otherVersion->file_path, 'other');

    $url = URL::temporarySignedRoute('downloads.show', now()->addMinutes(15), [
        'license' => $license->id,
        'version' => $otherVersion->id,
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('purchases.index', absolute: false));

    expect(Download::withoutGlobalScope('team')->count())->toBe(0);
});

test('releases published after the updates window are locked, earlier ones stay available', function () {
    [$user, , $license, $oldVersion] = licensedBuyer(['expires_at' => now()->subMonth()]);

    // Released before the window closed — still downloadable.
    $oldVersion->forceFill(['released_at' => now()->subMonths(3)])->save();

    // Released after it closed — locked.
    $newVersion = ProductVersion::factory()->for($oldVersion->product)->create([
        'version' => '2.0.0',
        'file_path' => 'releases/theme-2.0.0.zip',
        'released_at' => now()->subDay(),
    ]);
    Storage::disk(config('marketplace.releases_disk'))->put($newVersion->file_path, 'zip');

    expect($license->canDownload($oldVersion->fresh()))->toBeTrue()
        ->and($license->canDownload($newVersion))->toBeFalse();

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('downloads.show', now()->addMinutes(15), [
            'license' => $license->id,
            'version' => $newVersion->id,
        ]))
        ->assertRedirect(route('purchases.index', absolute: false));
});

test('the daily download limit is enforced per license', function () {
    [$user, $team, $license, $version] = licensedBuyer();

    $limit = (int) config('marketplace.downloads.daily_limit');

    app(CurrentTeam::class)->set($team);

    for ($i = 0; $i < $limit; $i++) {
        $download = new Download([
            'license_id' => $license->id,
            'product_version_id' => $version->id,
            'user_id' => $user->id,
        ]);
        $download->team()->associate($team);
        $download->save();
    }

    expect(app(AuthorizeDownload::class)->hasHitDailyLimit($license))->toBeTrue();

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('downloads.show', now()->addMinutes(15), [
            'license' => $license->id,
            'version' => $version->id,
        ]))
        ->assertRedirect(route('purchases.index', absolute: false));

    // Yesterday's downloads do not count against today.
    $this->travel(1)->days();

    expect(app(AuthorizeDownload::class)->hasHitDailyLimit($license->fresh()))->toBeFalse();
});

test('a missing release file returns a 404 rather than a broken stream', function () {
    [$user, , $license, $version] = licensedBuyer();

    Storage::disk(config('marketplace.releases_disk'))->delete($version->file_path);

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('downloads.show', now()->addMinutes(15), [
            'license' => $license->id,
            'version' => $version->id,
        ]))
        ->assertNotFound();

    expect(Download::withoutGlobalScope('team')->count())->toBe(0);
});
