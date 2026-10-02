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

        if (! $request->isMethod('get') || ! $request->is('/', 'admission', 'student-login', 'student/*') || $response->getStatusCode() !== 200 || ! method_exists($response, 'getContent')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = (string) $response->getContent();
        if ($html === '' || ! str_contains($html, '</head>') || ! str_contains($html, '</body>') || str_contains($html, 'data-cnet-app-installer')) {
            return $response;
        }
        $metadata = <<<'HTML'
<link rel="manifest" href="/library-app.webmanifest">
<meta name="theme-color" content="#0f766e">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="C-Net Library">
<link rel="apple-touch-icon" href="/library-app/icon/180.png">
<script src="/js/library-app-install.js?v=20261002" defer></script>
HTML;
        $html = str_replace('</head>', $metadata.'</head>', $html);
        $installer = view('public.app-installer')->render();
        $target = str_contains($html, '</footer>') ? '</footer>' : '</body>';
        $html = str_replace($target, $installer.$target, $html);
        $response->setContent($html);
        $response->headers->remove('Content-Length');

        return $response;
    }
}
