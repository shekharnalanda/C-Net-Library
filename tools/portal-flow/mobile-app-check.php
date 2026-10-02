<?php

use App\Http\Controllers\LibraryAppController;
use App\Http\Middleware\InjectMobileAppInstaller;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Use the real console kernel binding; report only reviewed app checks.
try {
    [$script, $root, $phase] = $argv;
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (parse_url((string) config('app.url'), PHP_URL_HOST) !== 'cnetlibrary.mciedu.com' || PHP_VERSION_ID < 80300) {
        throw new RuntimeException('Library identity or PHP version requires review.');
    }
    if (! function_exists('imagecreatefrompng') || ! function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('PHP GD PNG support is required for app icons.');
    }
    $source = public_path('images/cnet-library-icon.png');
    $dimensions = is_file($source) ? getimagesize($source) : false;
    if (! $dimensions || $dimensions[2] !== IMAGETYPE_PNG || max($dimensions[0], $dimensions[1]) > 4096 || filesize($source) > 2097152 || is_link($source)) {
        throw new RuntimeException('Existing library PNG icon requires review.');
    }
    if ($phase === 'verify') {
        $controller = app(LibraryAppController::class);
        $manifest = json_decode($controller->manifest()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if ($manifest['start_url'] !== '/student-login' || $manifest['display'] !== 'standalone') {
            throw new RuntimeException('App entry is invalid.');
        }
        foreach ([180, 192, 512] as $size) {
            $png = $controller->icon((string) $size);
            $actual = getimagesizefromstring($png->getContent());
            if (! $actual || $actual[0] !== $size || $actual[1] !== $size || $actual[2] !== IMAGETYPE_PNG) {
                throw new RuntimeException('App icon verification failed.');
            }
        }
        foreach (['library.app.manifest', 'library.app.icon', 'student.login'] as $name) {
            if (! Route::has($name)) {
                throw new RuntimeException('App route is missing: '.$name);
            }
        }
        $request = Request::create('https://cnetlibrary.mciedu.com/');
        $middleware = app(InjectMobileAppInstaller::class);
        $response = $middleware->handle($request, fn () => response('<html><head></head><body><footer></footer></body></html>'));
        if (! str_contains($response->getContent(), 'data-cnet-app-installer') || ! str_contains($response->getContent(), 'rel="manifest"')) {
            throw new RuntimeException('App installer rendering failed.');
        }
    }
    echo 'MOBILE_APP_CHECK_OK | '.$phase."\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'MOBILE_APP_CHECK_FAILED | '.get_class($e).' | '.$e->getMessage()."\n");
    exit(1);
}
