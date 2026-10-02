<?php

namespace Tests\Feature;

use App\Http\Middleware\InjectMobileAppInstaller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LibraryMobileAppTest extends TestCase
{
    public function test_manifest_uses_student_entry_and_square_icons(): void
    {
        $response = $this->get('/library-app.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $manifest = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('/student-login', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame(['192x192', '512x512'], array_column($manifest['icons'], 'sizes'));
    }

    public function test_icons_retain_real_brand_image_at_installable_sizes(): void
    {
        $old = public_path();
        $temporary = sys_get_temp_dir().'/cnet-icon-'.bin2hex(random_bytes(6));
        mkdir($temporary.'/images', 0700, true);
        $image = imagecreatetruecolor(240, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 90, 110));
        imagepng($image, $temporary.'/images/cnet-library-icon.png');
        imagedestroy($image);
        $this->app->usePublicPath($temporary);
        try {
            foreach ([180, 192, 512] as $size) {
                $png = $this->get('/library-app/icon/'.$size.'.png')->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
                $dimensions = getimagesizefromstring($png);
                $this->assertSame([$size, $size], array_slice($dimensions, 0, 2));
                $decoded = imagecreatefromstring($png);
                $this->assertSame((10 << 16) + (90 << 8) + 110, imagecolorat($decoded, (int) ($size / 2), (int) ($size / 2)));
                imagedestroy($decoded);
            }
            $this->get('/library-app/icon/999.png')->assertNotFound();
            unlink($temporary.'/images/cnet-library-icon.png');
            $this->get('/library-app/icon/192.png')->assertStatus(503);
        } finally {
            $this->app->usePublicPath($old);
            @unlink($temporary.'/images/cnet-library-icon.png');
            rmdir($temporary.'/images');
            rmdir($temporary);
        }
    }

    public function test_install_controls_appear_without_apk_on_student_pages_but_not_admin_or_posts(): void
    {
        $middleware = new InjectMobileAppInstaller;
        $body = '<html><head></head><body><footer></footer></body></html>';
        foreach (['/', '/admission', '/student-login', '/student/dashboard'] as $path) {
            $response = $middleware->handle(Request::create($path), fn () => new Response($body, 200, ['Content-Type' => 'text/html', 'Cache-Control' => 'no-store', 'Content-Length' => strlen($body)]));
            $this->assertStringContainsString('rel="manifest"', $response->getContent());
            $this->assertStringContainsString('data-cnet-app-installer', $response->getContent());
            $this->assertStringNotContainsString('.apk', $response->getContent());
            $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
            $this->assertFalse($response->headers->has('Content-Length'));
            $again = $middleware->handle(Request::create($path), fn () => $response);
            $this->assertSame(1, substr_count($again->getContent(), 'data-cnet-app-installer'));
        }
        foreach ([['/login', 'GET', 200], ['/admin/dashboard', 'GET', 200], ['/admission', 'POST', 200], ['/', 'GET', 503]] as [$path, $method, $code]) {
            $response = $middleware->handle(Request::create($path, $method), fn () => new Response($body, $code, ['Content-Type' => 'text/html']));
            $this->assertSame($body, $response->getContent());
        }
    }
}
