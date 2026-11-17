<?php

namespace App\Support;

/**
 * Builds public URLs for media stored by the app.
 *
 * Accepts a bare path ("services/x.jpg"), a mounted public path
 * ("/storage/services/x.jpg") or an already-absolute URL (seed data points at
 * external CDNs). Only relative paths are prefixed, so a full URL is never
 * turned into "/storage/https://...".
 */
class PublicUrl
{
    public static function storage(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = preg_replace('#^/?storage/#i', '', $path);

        return asset('storage/' . ltrim((string) $path, '/'));
    }
}
