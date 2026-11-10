<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Controller;

use OCA\PdfEditor\Service\PdfCraftBundleService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Files\NotFoundException;
use OCP\IRequest;

/**
 * Serves the vendored PdfCraft web build.
 *
 * The bundle is a static site; this controller is the only way it reaches the
 * browser, so the app does not depend on the web server being configured to
 * expose an app subdirectory. Each response carries the content type the
 * browser needs - notably `application/wasm`, without which the module is
 * refused.
 *
 * Public, like the app's own scripts: the bundle contains no user data, and
 * requiring a session here would break the iframe's subresource requests,
 * which carry no CSRF token.
 */
class PdfCraftController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly PdfCraftBundleService $bundle,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Stream one file from the bundle.
     *
     * @param string $path Relative path inside the bundle, e.g. `index.html`.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function asset(string $path = ''): Response
    {
        try {
            $absolute = $this->bundle->resolve($path);
        } catch (NotFoundException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        }

        $cacheControl = $this->bundle->cacheControlFor($path);
        $etag = $this->etagFor($absolute);

        // The entry point and the OCR models keep their names across releases,
        // so they revalidate rather than being cached indefinitely. Without a
        // validator the browser re-downloads the ~12 MB of models on every
        // visit; answering a matching If-None-Match with 304 lets it skip that.
        if ($this->request->getHeader('If-None-Match') === $etag) {
            $notModified = new DataResponse(null, Http::STATUS_NOT_MODIFIED);
            $notModified->addHeader('ETag', $etag);
            $notModified->addHeader('Cache-Control', $cacheControl);

            return $notModified;
        }

        $stream = fopen($absolute, 'rb');
        if ($stream === false) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        }

        $response = new StreamResponse($stream);
        // The type comes from the file that was resolved, not the requested
        // path: a request for the bundle root maps to `index.html`, and the
        // empty path would otherwise be served as octet-stream.
        $response->addHeader('Content-Type', $this->bundle->contentTypeFor($absolute));
        $response->addHeader('X-Content-Type-Options', 'nosniff');
        $response->addHeader('Cache-Control', $cacheControl);
        $response->addHeader('ETag', $etag);

        // The bundle's index.html boots the WASM module from an inline module
        // script and its own hashed .js/.wasm files, and PdfCraft starts Web
        // Workers for OCR. Nextcloud's default policy is `default-src 'none'`
        // with a nonce-only script-src, which blocks both. The document is
        // first-party (served from this app, no user data), so it is given a
        // policy scoped to what it actually loads rather than the site default.
        if (strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) === 'html') {
            $response->setContentSecurityPolicy($this->bundleContentSecurityPolicy());
        }

        return $response;
    }

    /**
     * CSP for the bundle's HTML document.
     *
     * `'unsafe-inline'` for scripts is required because the bootstrap is an
     * inline `<script type="module">` in the generated `index.html`, which
     * cannot carry a nonce (the file is vendored, not templated here).
     * `worker-src 'self' blob:` allows the OCR workers, and `wasm-unsafe-eval`
     * lets the module be compiled. Everything else stays at the default
     * `default-src 'none'`, so the document can only load its own assets.
     */
    private function bundleContentSecurityPolicy(): ContentSecurityPolicy
    {
        $policy = new ContentSecurityPolicy();
        $policy->addAllowedScriptDomain("'self'");
        $policy->addAllowedScriptDomain("'unsafe-inline'");
        $policy->addAllowedStyleDomain("'self'");
        $policy->addAllowedStyleDomain("'unsafe-inline'");
        $policy->addAllowedConnectDomain("'self'");
        $policy->addAllowedImageDomain("'self'");
        $policy->addAllowedImageDomain('data:');
        $policy->addAllowedImageDomain('blob:');
        $policy->addAllowedFontDomain("'self'");
        $policy->addAllowedFontDomain('data:');
        $policy->addAllowedWorkerSrcDomain("'self'");
        $policy->addAllowedWorkerSrcDomain('blob:');
        $policy->allowEvalWasm();
        // The editor is embedded in the app's own page, so the document must
        // allow being framed by this Nextcloud instance.
        $policy->addAllowedFrameAncestorDomain("'self'");

        return $policy;
    }

    /**
     * A validator for a bundle file, derived from its size and mtime.
     *
     * The bundle is written once by `scripts/fetch-pdfcraft.sh` and replaced
     * whole on an upgrade, so size and mtime change together with the content
     * for a file that keeps its name.
     */
    private function etagFor(string $absolute): string
    {
        $size = @filesize($absolute);
        $mtime = @filemtime($absolute);

        return '"' . md5(($size === false ? 0 : $size) . '-' . ($mtime === false ? 0 : $mtime)) . '"';
    }
}
