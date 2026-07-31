<?php

namespace App\Actions\Marketplace;

use App\Models\Download;
use App\Models\License;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single gate every release download passes through. Decides
 * whether a license may fetch a given version right now, and records
 * the attempt.
 */
class AuthorizeDownload
{
    /**
     * @throws ValidationException when the download must not proceed
     */
    public function authorize(License $license, ProductVersion $version): void
    {
        if ($version->product_id !== $license->product_id) {
            throw ValidationException::withMessages([
                'download' => __('That file does not belong to this license.'),
            ]);
        }

        if ($license->isRevoked()) {
            throw ValidationException::withMessages([
                'download' => __('This license has been revoked.'),
            ]);
        }

        if (! $license->canDownload($version)) {
            throw ValidationException::withMessages([
                'download' => __('Your updates period ended before this version was released. Renew to download it.'),
            ]);
        }

        if ($this->hasHitDailyLimit($license)) {
            throw ValidationException::withMessages([
                'download' => __('Daily download limit reached for this license. Try again tomorrow.'),
            ]);
        }
    }

    /**
     * Guards against a leaked key being used to mirror the files.
     */
    public function hasHitDailyLimit(License $license): bool
    {
        $limit = (int) config('marketplace.downloads.daily_limit');

        if ($limit <= 0) {
            return false;
        }

        return $this->downloadsToday($license) >= $limit;
    }

    public function downloadsToday(License $license): int
    {
        return Download::acrossTeams()
            ->where('license_id', $license->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * Writes the audit row and bumps the product's public counter.
     */
    public function record(License $license, ProductVersion $version, ?User $user, ?string $ip): Download
    {
        return DB::transaction(function () use ($license, $version, $user, $ip): Download {
            $download = new Download([
                'license_id' => $license->id,
                'product_version_id' => $version->id,
                'user_id' => $user?->id,
                'ip' => $ip,
            ]);

            $download->team()->associate($license->team_id);
            $download->save();

            $version->product()->increment('downloads_count');

            return $download;
        });
    }
}
