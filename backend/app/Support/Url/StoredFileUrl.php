<?php

namespace App\Support\Url;

use App\Support\Media\MediaStorage;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * URL resolution for stored media paths.
 *
 * Which channel a path belongs to is decided by MediaStorage from the path's
 * own prefix (uploads/ vs requests/), so a caller only ever needs the path.
 */
final class StoredFileUrl
{
    public const DEFAULT_EXPIRY_DAYS = 7;

    /**
     * URL for external recipients (e.g. mail links).
     *
     * Public media resolves to the CDN/storage URL; private media to a link
     * that still requires authentication or a signature to open.
     */
    public static function resolve(string $path, ?DateTimeInterface $expiresAt = null): string
    {
        if (MediaStorage::isPrivatePath($path)) {
            return self::resolvePrivateUrl(
                $path,
                MediaStorage::privateDisk(),
                $expiresAt ?? now()->addDays(self::DEFAULT_EXPIRY_DAYS),
            );
        }

        return self::resolvePublicUrl($path, MediaStorage::publicDisk());
    }

    /**
     * URL for Backpack admin previews (session-authenticated stream route).
     */
    public static function resolveForAdminPreview(string $path): string
    {
        // Which channel a path belongs to is MediaStorage's to decide, from the
        // path's own prefix: re-deriving it here (e.g. checking for a leading
        // 'logos/' while stored paths start with 'uploads/logos/') sends public
        // assets to the private stream route, where they render broken.
        if (MediaStorage::isPrivatePath($path)) {
            if (MediaStorage::privateDisk() === 's3') {
                return Storage::disk('s3')->temporaryUrl($path, now()->addHours(12));
            }

            // Locally: streamed by StoredFileController, behind admin auth.
            return route('media-stream.show', ['path' => $path]);
        }

        // Through resolvePublicUrl rather than straight to the disk URL: on S3
        // without a CDN configured, a plain disk URL points at the bucket and
        // only opens if the object is publicly readable. Buckets are private —
        // no ACLs, nothing public — so that produces an S3 AccessDenied page in
        // place of the file. resolvePublicUrl already falls back to a signed
        // URL in that case, which works on a private bucket; when the CDN is
        // configured it returns the CDN link.
        return self::resolvePublicUrl($path, MediaStorage::publicDisk());
    }

    /**
     * Whether the path belongs to the private channel (see MediaStorage).
     */
    public static function isPrivatePath(string $path): bool
    {
        return MediaStorage::isPrivatePath($path);
    }

    /**
     * URL of a public path: CDN on S3 (signed URL when no CDN is set), the
     * disk URL locally.
     */
    private static function resolvePublicUrl(string $path, string $disk): string
    {
        if ($disk === 's3') {
            $cdnUrl = rtrim((string) config('filesystems.disks.s3.url'), '/');
            if ($cdnUrl !== '') {
                return $cdnUrl.'/'.ltrim($path, '/');
            }

            return Storage::disk('s3')->temporaryUrl($path, now()->addDays(self::DEFAULT_EXPIRY_DAYS));
        }

        if (! self::isPrivatePath($path)) {
            return self::uploadsPublicUrl($path);
        }

        return URL::temporarySignedRoute(
            'stored-file.show',
            now()->addDays(self::DEFAULT_EXPIRY_DAYS),
            ['path' => $path]
        );
    }

    /**
     * Expiring URL of a private path: S3 temporary URL, or the signed
     * stored-file route locally.
     */
    private static function resolvePrivateUrl(
        string $path,
        string $disk,
        DateTimeInterface $expiresAt,
    ): string {
        if ($disk === 's3') {
            return Storage::disk('s3')->temporaryUrl($path, $expiresAt);
        }

        return URL::temporarySignedRoute('stored-file.show', $expiresAt, ['path' => $path]);
    }

    /**
     * Public URL for a path on a local disk.
     *
     * Legacy records were written with roots that no longer match the current
     * ones, so the candidate disks are tried in order and the first that both
     * exposes a URL and actually holds the file wins. A path in the current
     * layout is trusted without the existence check, which is what
     * MediaStorage::candidateDisks() and diskUrl() are built around.
     */
    private static function uploadsPublicUrl(string $path): string
    {
        foreach (MediaStorage::candidateDisks($path) as $disk) {
            $url = self::diskUrl($disk, $path);

            if ($url !== null) {
                return $url;
            }
        }

        return Storage::disk(MediaStorage::publicDisk())->url(ltrim($path, '/'));
    }

    /**
     * Disk URL for a path, or null when the disk exposes no public URL or does
     * not hold the file (legacy records live on older disks).
     */
    private static function diskUrl(string $disk, string $path): ?string
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config) || empty($config['url'])) {
            return null;
        }

        // Legacy paths may sit on any of the candidate disks, so only build a
        // URL from one that actually has the file. Paths in the current layout
        // are trusted as-is: a missing file yields a broken URL rather than
        // silently resolving to a different disk.
        if (! MediaStorage::isCurrentLayout($path) && ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return rtrim((string) $config['url'], '/').'/'.$path;
    }
}
