<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    return response()->json(['message' => 'AhaTask API is Up and Running!']);
});

Route::get('/api/documentation', function () {
        $docsUrl = route('l5-swagger.default.docs', [], true);
        $assetCss = route('l5-swagger.default.asset', ['asset' => 'swagger-ui.css'], true);
        $assetBundle = route('l5-swagger.default.asset', ['asset' => 'swagger-ui-bundle.js'], true);
        $assetPreset = route('l5-swagger.default.asset', ['asset' => 'swagger-ui-standalone-preset.js'], true);
        $favicon32 = route('l5-swagger.default.asset', ['asset' => 'favicon-32x32.png'], true);
        $favicon16 = route('l5-swagger.default.asset', ['asset' => 'favicon-16x16.png'], true);

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>AhaTask API Documentation</title>
    <link rel="stylesheet" href="{$assetCss}" />
    <link rel="icon" type="image/png" href="{$favicon32}" sizes="32x32" />
    <link rel="icon" type="image/png" href="{$favicon16}" sizes="16x16" />
    <style>
        html, body { margin: 0; padding: 0; }
        body { background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="{$assetBundle}"></script>
    <script src="{$assetPreset}"></script>
    <script>
        window.onload = function () {
            SwaggerUIBundle({
                dom_id: '#swagger-ui',
                url: '{$docsUrl}',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: 'StandaloneLayout'
            });
        };
    </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
});
