<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectMobileAppInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->isMethod('get') || $request->path() !== '/' || ! method_exists($response, 'getContent')) {
            return $response;
        }

        $apkRelativePath = 'downloads/C-Net-Library.apk';
        $apkPath = public_path($apkRelativePath);

        if (! is_file($apkPath)) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = (string) $response->getContent();
        if ($html === '' || str_contains($html, 'data-cnet-app-installer')) {
            return $response;
        }

        $sizeMb = number_format(filesize($apkPath) / 1048576, 1);
        $downloadUrl = asset($apkRelativePath);

        $installer = <<<HTML
<div data-cnet-app-installer style="margin-top:20px;padding-top:18px;border-top:1px solid rgba(255,255,255,.18)">
  <strong style="display:block;font-size:16px;margin-bottom:7px">C-Net Library Mobile App</strong>
  <div style="font-size:13px;opacity:.86;margin-bottom:12px">Android app • {$sizeMb} MB • Direct secure download</div>
  <a href="{$downloadUrl}" download="C-Net-Library.apk" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#ffffff;color:#0f766e;text-decoration:none;font-weight:800;padding:11px 16px;border-radius:10px;border:1px solid #ffffff">📱 Download &amp; Install App</a>
  <div style="font-size:12px;opacity:.72;margin-top:9px">Android may ask permission to install apps downloaded from your browser. Allow it for this download, then open the APK to install.</div>
</div>
HTML;

        $html = str_replace('</footer>', $installer.'</footer>', $html);
        $response->setContent($html);

        return $response;
    }
}
