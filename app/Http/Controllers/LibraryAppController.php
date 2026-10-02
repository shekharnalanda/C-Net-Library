<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class LibraryAppController extends Controller
{
    public function manifest(): Response
    {
        $manifest = [
            'id' => '/', 'name' => 'C-Net Library', 'short_name' => 'C-Net Library',
            'description' => 'Library admission, seats, fees, digital ID and online practice.',
            'start_url' => '/student-login', 'scope' => '/', 'display' => 'standalone',
            'background_color' => '#ecfdf5', 'theme_color' => '#0f766e',
            'icons' => array_map(fn ($size) => ['src' => '/library-app/icon/'.$size.'.png', 'sizes' => $size.'x'.$size, 'type' => 'image/png', 'purpose' => 'any'], [192, 512]),
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=300']);
    }

    public function icon(string $size): Response
    {
        abort_unless(in_array($size, ['180', '192', '512'], true), 404);
        $source = public_path('images/cnet-library-icon.png');
        abort_unless(is_file($source) && ! is_link($source) && filesize($source) <= 2097152 && function_exists('imagecreatefrompng'), 503);
        $dimensions = getimagesize($source);
        abort_unless($dimensions && $dimensions[2] === IMAGETYPE_PNG && $dimensions[0] > 0 && $dimensions[1] > 0 && max($dimensions[0], $dimensions[1]) <= 4096, 503);
        $image = imagecreatefrompng($source);
        abort_unless($image !== false, 503);
        $pixels = (int) $size;
        $canvas = imagecreatetruecolor($pixels, $pixels);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $scale = min($pixels / $dimensions[0], $pixels / $dimensions[1]);
        $width = max(1, (int) round($dimensions[0] * $scale));
        $height = max(1, (int) round($dimensions[1] * $scale));
        imagecopyresampled($canvas, $image, (int) (($pixels - $width) / 2), (int) (($pixels - $height) / 2), 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($image);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=3600']);
    }
}
