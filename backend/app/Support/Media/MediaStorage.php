<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Single entry point for stored media (images, logos, fonts, attachments).
 *
 * Two channels, same layout:
 *
 *   uploads/{tipo}/{owner}/{file}   public  — what we publish ourselves
 *   requests/{tipo}/{owner}/{file}  private — what users submit, pending approval
 *
 * {owner} is an optional folder segment grouping the files of one owner
 * (e.g. a tenant or a user); it is left out when null.
 *
 * The path stored in the database is always relative to the disk root and
 * carries its own prefix, so it is byte-identical on local and S3 and tells you
 * which channel it belongs to without a lookup:
 *
 *   local  public   storage/app/public + /uploads/... → {APP_URL}/storage/uploads/...
 *   local  private  storage/app       + /requests/... → admin-only stream route
 *   s3     public   {AWS_PREFIX}      + /uploads/... → {CLOUDFRONT}/uploads/...
 *   s3     private  {AWS_PREFIX}      + /requests/... → temporary signed URL
 */
final class MediaStorage
{
    public const PUBLIC_PREFIX = 'uploads';

    public const PRIVATE_PREFIX = 'requests';

    /**
     * Legacy prefixes from before the layout was unified. Read-only: nothing
     * writes there any more, but stored rows still point at them.
     *
     * @var array<string, string> prefix => channel
     */
    private const LEGACY_PREFIXES = [
        'logos/' => 'public',
        'fonts/' => 'public',
        'media/' => 'public',
    ];

    /**
     * Disk holding publicly served media (CloudFront on S3, the storage symlink
     * locally).
     */
    public static function publicDisk(): string
    {
        return self::usesS3() ? 's3' : 'public';
    }

    /**
     * Disk holding media that must never be publicly reachable.
     */
    public static function privateDisk(): string
    {
        return self::usesS3() ? 's3' : 'local';
    }

    public static function diskFor(string $path): string
    {
        return self::isPrivatePath($path) ? self::privateDisk() : self::publicDisk();
    }

    /**
     * Store a public asset we publish ourselves.
     *
     * @param  string  $tipo  logos, fonts, media, …
     * @param  string|null  $owner  Optional owner folder segment
     */
    public static function storePublic(UploadedFile $file, string $tipo, ?string $owner = null): string
    {
        return self::store($file, self::PUBLIC_PREFIX, $tipo, $owner, self::publicDisk(), 'public');
    }

    /**
     * Store an asset submitted by a user, pending our approval.
     *
     * @param  string|null  $owner  Optional owner folder segment
     */
    public static function storePrivate(UploadedFile $file, string $tipo, ?string $owner = null): string
    {
        return self::store($file, self::PRIVATE_PREFIX, $tipo, $owner, self::privateDisk(), 'private');
    }

    public static function isPrivatePath(string $path): bool
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, self::PRIVATE_PREFIX.'/')) {
            return true;
        }

        foreach (self::LEGACY_PREFIXES as $prefix => $channel) {
            if ($channel === 'private' && str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the path is one this class writes, as opposed to a legacy one.
     */
    public static function isCurrentLayout(string $path): bool
    {
        $path = ltrim($path, '/');

        return str_starts_with($path, self::PUBLIC_PREFIX.'/')
            || str_starts_with($path, self::PRIVATE_PREFIX.'/');
    }

    /**
     * Disks a legacy path may live on, most likely first. Old records were
     * written with roots that no longer match the current ones.
     *
     * @return list<string>
     */
    public static function candidateDisks(string $path): array
    {
        if (self::isCurrentLayout($path)) {
            return [self::diskFor($path)];
        }

        // Legacy public media was written on the 'uploads' disk (rooted at
        // storage/app/public/uploads), legacy private media on 'local'.
        return array_values(array_unique([
            self::diskFor($path),
            'uploads',
            'local',
            (string) config('filesystems.default'),
        ]));
    }

    private static function store(
        UploadedFile $file,
        string $prefix,
        string $tipo,
        ?string $owner,
        string $disk,
        string $visibility,
    ): string {
        $directory = trim($prefix.'/'.$tipo.'/'.self::segment($owner), '/');

        $options = [
            'disk' => $disk,
            'ServerSideEncryption' => 'AES256',
        ];

        // Visibility turns into an ACL header on S3, and buckets created since
        // April 2023 default to Object Ownership "bucket owner enforced", where
        // ACLs are disabled and any PutObject carrying one is refused outright
        // with AccessDenied — for 'private' just as much as for 'public'.
        //
        // Nothing here needs them: the public channel is served through
        // CloudFront and the private one is reached with a signed URL, so what
        // keeps them apart is the prefix and the bucket policy, never an ACL.
        // On a local disk it still decides file permissions, so it stays.
        if (! self::usesS3()) {
            $options['visibility'] = $visibility;
        }

        return (string) $file->storeAs($directory, self::filename($file), $options);
    }

    /**
     * Prefix the sanitised original name with a uuid: two owners uploading
     * "logo.png" must not overwrite each other, and neither must two successive
     * uploads from the same owner while one is pending review.
     */
    private static function filename(UploadedFile $file): string
    {
        $original = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $file->getClientOriginalName());

        return Str::uuid()->toString().'_'.$original;
    }

    private static function segment(?string $value): string
    {
        $segment = preg_replace('/[^A-Za-z0-9\-\_]/', '', (string) $value);

        return $segment === '' ? 'unknown' : $segment;
    }

    private static function usesS3(): bool
    {
        return config('filesystems.default') === 's3';
    }
}
