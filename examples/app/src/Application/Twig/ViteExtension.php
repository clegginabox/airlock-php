<?php

declare(strict_types=1);

namespace App\Application\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ViteExtension extends AbstractExtension
{
    private ?array $manifest = null;

    private ?int $manifestMtime = null;

    public function __construct(
        private readonly string $publicPath,
        private readonly bool $isDev,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('vite', [$this, 'vite'], ['is_safe' => ['html']]),
        ];
    }

    public function vite(string ...$entries): string
    {
        if ($this->isDev) {
            return $this->devTags($entries);
        }

        return $this->prodTags($entries);
    }

    private function devTags(array $entries): string
    {
        $html = '<script type="module" src="http://localhost:5173/@vite/client"></script>' . "\n";

        foreach ($entries as $entry) {
            $html .= '<script type="module" src="http://localhost:5173/' . $entry . '"></script>' . "\n";
        }

        return $html;
    }

    private function prodTags(array $entries): string
    {
        $manifest = $this->loadManifest();
        $html = '';
        $emitted = [];

        foreach ($entries as $entry) {
            if (!isset($manifest[$entry])) {
                continue;
            }

            $html .= $this->emitChunk($manifest[$entry], $manifest, $emitted);
        }

        return $html;
    }

    private function emitChunk(array $chunk, array $manifest, array &$emitted): string
    {
        $html = '';

        // Emit shared imports first (e.g. alerts.js)
        foreach ($chunk['imports'] ?? [] as $importKey) {
            if (isset($emitted[$importKey]) || !isset($manifest[$importKey])) {
                continue;
            }

            $emitted[$importKey] = true;
            $html .= $this->emitChunk($manifest[$importKey], $manifest, $emitted);
        }

        // Emit CSS files
        foreach ($chunk['css'] ?? [] as $cssFile) {
            if (isset($emitted[$cssFile])) {
                continue;
            }

            $html .= '<link rel="stylesheet" href="/build/' . $cssFile . '">' . "\n";
            $emitted[$cssFile] = true;
        }

        // Emit the JS file
        if (isset($chunk['file']) && !isset($emitted[$chunk['file']])) {
            $html .= '<script type="module" src="/build/' . $chunk['file'] . '"></script>' . "\n";
            $emitted[$chunk['file']] = true;
        }

        return $html;
    }

    private function loadManifest(): array
    {
        $path = $this->publicPath . '/build/.vite/manifest.json';

        if (!file_exists($path)) {
            $this->manifestMtime = null;
            return $this->manifest = [];
        }

        $mtime = filemtime($path);
        if ($this->manifest !== null && $this->manifestMtime === $mtime) {
            return $this->manifest;
        }

        $this->manifestMtime = $mtime === false ? null : $mtime;
        $this->manifest = json_decode(file_get_contents($path), true) ?: [];

        return $this->manifest;
    }
}
