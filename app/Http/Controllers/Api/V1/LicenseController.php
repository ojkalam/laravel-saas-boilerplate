<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Marketplace\AuthorizeDownload;
use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-to-server endpoints for installed themes and apps. These are
 * authenticated by the license key itself — an installed copy has no
 * session — so every route is throttled and returns as little as
 * possible about keys it does not recognise.
 */
class LicenseController extends Controller
{
    public function activate(Request $request): JsonResponse
    {
        $data = $this->validated($request, requiresInstance: true);

        $license = $this->resolve($data['key']);

        if ($license === null) {
            return $this->unknownKey();
        }

        if (! $license->isActive()) {
            return response()->json([
                'activated' => false,
                'message' => __('This license is not active.'),
            ], 403);
        }

        $existing = $license->activations()->where('instance', $data['instance'])->first();

        // Re-activating the same install is a no-op, not an error: an
        // app may re-register after a redeploy.
        if ($existing !== null) {
            return response()->json([
                'activated' => true,
                'instance' => $existing->instance,
                'activations_used' => $license->activations()->count(),
                'activation_limit' => $license->activation_limit,
            ]);
        }

        if (! $license->hasActivationsRemaining()) {
            return response()->json([
                'activated' => false,
                'message' => __('Activation limit reached. Release another install first.'),
                'activations_used' => $license->activations()->count(),
                'activation_limit' => $license->activation_limit,
            ], 422);
        }

        $license->activations()->create([
            'instance' => $data['instance'],
            'activated_at' => now(),
        ]);

        return response()->json([
            'activated' => true,
            'instance' => $data['instance'],
            'activations_used' => $license->activations()->count(),
            'activation_limit' => $license->activation_limit,
        ], 201);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $data = $this->validated($request, requiresInstance: true);

        $license = $this->resolve($data['key']);

        if ($license === null) {
            return $this->unknownKey();
        }

        $license->activations()->where('instance', $data['instance'])->delete();

        return response()->json([
            'deactivated' => true,
            'activations_used' => $license->activations()->count(),
            'activation_limit' => $license->activation_limit,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $license = $this->resolve($data['key']);

        if ($license === null) {
            return $this->unknownKey();
        }

        $latest = $license->product->latestVersion();

        return response()->json([
            'valid' => $license->isActive(),
            'status' => $license->status->value,
            'product' => [
                'name' => $license->product->name,
                'slug' => $license->product->slug,
            ],
            'updates_until' => $license->expires_at?->toIso8601String(),
            'has_updates_access' => $license->hasUpdatesAccess(),
            'activations_used' => $license->activations()->count(),
            'activation_limit' => $license->activation_limit,
            'activated_here' => isset($data['instance'])
                ? $license->activations()->where('instance', $data['instance'])->exists()
                : null,
            'latest_version' => $latest?->version,
        ]);
    }

    /**
     * What an auto-updater polls: the newest release this license may
     * actually download, not merely the newest that exists.
     */
    public function latestVersion(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $license = $this->resolve($data['key']);

        if ($license === null) {
            return $this->unknownKey();
        }

        $latest = $license->product->latestVersion();

        if ($latest === null) {
            return response()->json(['message' => __('No releases published.')], 404);
        }

        $downloadable = $license->canDownload($latest);

        return response()->json([
            'version' => $latest->version,
            'released_at' => $latest->released_at?->toIso8601String(),
            'changelog' => $latest->changelog,
            'size' => $latest->file_size,
            'downloadable' => $downloadable,
            'download_url' => $downloadable
                ? route('api.license.download', ['key' => $license->key, 'version' => $latest->version])
                : null,
            'message' => $downloadable
                ? null
                : __('Your updates period has ended. Renew to receive this release.'),
        ]);
    }

    /**
     * Key-authenticated download for auto-updaters. Runs exactly the
     * same authorization and auditing as the buyer portal.
     */
    public function download(Request $request, AuthorizeDownload $authorizer): StreamedResponse|JsonResponse
    {
        $data = $this->validated($request);

        $license = $this->resolve($data['key']);

        if ($license === null) {
            return $this->unknownKey();
        }

        $version = $request->filled('version')
            ? $license->product->versions()->where('version', $request->string('version')->toString())->first()
            : $license->product->latestVersion();

        if ($version === null) {
            return response()->json(['message' => __('Version not found.')], 404);
        }

        try {
            $authorizer->authorize($license, $version);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $disk = Storage::disk(config('marketplace.releases_disk'));

        if (! $disk->exists($version->file_path)) {
            return response()->json(['message' => __('Release file missing.')], 404);
        }

        $authorizer->record($license, $version, null, $request->ip());

        return $disk->download(
            $version->file_path,
            $license->product->slug.'-'.$version->version.'.zip',
        );
    }

    /**
     * The instance key is always present so callers can read it
     * directly; it is null when the endpoint does not require one.
     *
     * @return array{key: string, instance: string|null}
     */
    protected function validated(Request $request, bool $requiresInstance = false): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'instance' => [$requiresInstance ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        $data['key'] = Str::upper(trim($data['key']));
        $data['instance'] ??= null;

        if ($data['instance'] !== null) {
            // Normalize so "https://Example.com/" and "example.com"
            // are the same install rather than two seats.
            $instance = Str::lower(trim($data['instance']));
            $instance = preg_replace('#^https?://#', '', $instance) ?? $instance;
            $data['instance'] = rtrim($instance, '/');
        }

        return $data;
    }

    protected function resolve(string $key): ?License
    {
        // No team context on these routes, so the tenant scope must be
        // lifted deliberately — the key itself is the credential.
        return License::acrossTeams()
            ->with('product')
            ->where('key', $key)
            ->first();
    }

    protected function unknownKey(): JsonResponse
    {
        return response()->json([
            'valid' => false,
            'message' => __('Unknown license key.'),
        ], 404);
    }
}
