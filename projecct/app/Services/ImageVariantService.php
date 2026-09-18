<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageVariantService
{
    /**
     * Yerel (public disk) bir görsel için card (800x600) ve thumb (400x300)
     * webp variant'ları üretir. Orijinal dosyaya dokunmaz.
     *
     * @return array{card: ?string, thumb: ?string} public disk'e göre relative yollar
     */
    public static function generate(string $originalPath): array
    {
        $result = ['card' => null, 'thumb' => null];

        // Uzak URL veya yerelde olmayan dosyaları atla (seed'deki Unsplash URL'leri gibi).
        if (preg_match('#^https?://#i', $originalPath)) {
            return $result;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($originalPath)) {
            return $result;
        }

        try {
            $manager = new ImageManager(new Driver());
            $absolute = $disk->path($originalPath);

            $info = pathinfo($originalPath);
            $base = ($info['dirname'] === '.' ? '' : $info['dirname'].'/').$info['filename'];

            foreach ([['card', 800, 600], ['thumb', 400, 300]] as [$key, $w, $h]) {
                $image = $manager->read($absolute);
                $image->coverDown($w, $h);
                $rel = $base.'_'.$key.'.webp';
                $disk->put($rel, (string) $image->toWebp(80));
                $result[$key] = $rel;
            }
        } catch (\Throwable $e) {
            Log::warning('ImageVariantService: variant üretilemedi ['.$originalPath.'] '.$e->getMessage());
            return ['card' => null, 'thumb' => null];
        }

        return $result;
    }
}
