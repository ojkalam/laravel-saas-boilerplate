<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\Marketplace\AuthorizeDownload;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\ProductVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Release files are never publicly reachable. A buyer asks for a link,
 * we authorize and mint a short-lived signed URL, and the file is
 * streamed only when that URL is redeemed.
 */
class DownloadController extends Controller
{
    /**
     * Step 1 — authorize, then hand out a signed URL valid for minutes.
     */
    public function create(
        Request $request,
        License $license,
        ProductVersion $version,
        AuthorizeDownload $authorizer,
    ): RedirectResponse {
        $this->assertLicenseBelongsToCurrentTeam($request, $license);

        try {
            $authorizer->authorize($license, $version);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to(URL::temporarySignedRoute(
            'downloads.show',
            now()->addMinutes((int) config('marketplace.downloads.link_ttl_minutes')),
            ['license' => $license->id, 'version' => $version->id],
        ));
    }

    /**
     * Step 2 — redeem the signed URL. Re-checks everything: a signature
     * only proves the link was issued, not that it is still allowed.
     */
    public function show(
        Request $request,
        License $license,
        ProductVersion $version,
        AuthorizeDownload $authorizer,
    ): StreamedResponse|RedirectResponse {
        $this->assertLicenseBelongsToCurrentTeam($request, $license);

        try {
            $authorizer->authorize($license, $version);
        } catch (ValidationException $e) {
            return redirect()->route('purchases.index')->with('error', $e->getMessage());
        }

        $disk = Storage::disk(config('marketplace.releases_disk'));

        abort_unless($disk->exists($version->file_path), 404);

        $authorizer->record($license, $version, $request->user(), $request->ip());

        return $disk->download(
            $version->file_path,
            $this->filename($license, $version),
        );
    }

    protected function assertLicenseBelongsToCurrentTeam(Request $request, License $license): void
    {
        // Whether route model binding is already team-scoped depends on
        // middleware ordering, so do not rely on it: this check is what
        // guarantees one team can never redeem another's license.
        abort_unless($request->user()?->current_team_id === $license->team_id, 403);
    }

    protected function filename(License $license, ProductVersion $version): string
    {
        return $version->product->slug.'-'.$version->version.'.zip';
    }
}
