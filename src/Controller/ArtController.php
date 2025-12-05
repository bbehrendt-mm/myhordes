<?php

namespace App\Controller;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class ArtController extends CustomAbstractController
{
    private const ALLOWED_ROLES = ['ROLE_ART', 'ROLE_ADMIN', 'ROLE_SUB_ADMIN', 'ROLE_SUPER', 'ROLE_DEV'];

    private function isArtAllowed(): bool
    {
        foreach (self::ALLOWED_ROLES as $role) {
            if ($this->isGranted($role)) {
                return true;
            }
        }
        return false;
    }

    private function denyUnlessArt(): ?Response
    {
        if (!$this->isArtAllowed()) {
            return new Response('', 403);
        }
        return null;
    }

    #[Route(path: 'jx/art', name: 'art_dashboard', condition: 'request.isXmlHttpRequest()')]
    public function dashboard(): Response
    {
        if ($resp = $this->denyUnlessArt()) {
            return $resp;
        }

        return $this->render('ajax/art/dashboard.html.twig', $this->addDefaultTwigArgs(null, []));
    }

    #[Route(path: 'jx/art/assets', name: 'art_assets', condition: 'request.isXmlHttpRequest()')]
    public function assets(): Response
    {
        if ($resp = $this->denyUnlessArt()) {
            return $resp;
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $basePath = "{$projectDir}/assets/img";

        $finder = new Finder();
        $finder
            ->files()
            ->in($basePath)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->filter(fn(\SplFileInfo $f) => in_array(strtolower($f->getExtension()), ['png','jpg','jpeg','gif','svg','webp']))
            ->sortByName(true);

        $assets = [];
        foreach ($finder as $file) {
            $relative = ltrim(str_replace($basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);
            $parts = explode(DIRECTORY_SEPARATOR, $relative);
            $top = array_shift($parts);
            $rest = implode('/', $parts);
            $assets[$top][] = $rest === '' ? $top : $rest;
        }

        return $this->render('ajax/art/assets.html.twig', $this->addDefaultTwigArgs(null, [
            'assets' => $assets,
        ]));
    }

    #[Route(path: 'jx/art/asset', name: 'art_asset_file')]
    public function assetFile(Request $request): Response
    {
        if ($resp = $this->denyUnlessArt()) {
            return $resp;
        }

        $path = $request->query->get('p', '');
        if ($path === '' || str_contains($path, '..')) {
            throw new FileNotFoundException($path);
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $fullPath = realpath("{$projectDir}/assets/img/{$path}");
        $basePath = realpath("{$projectDir}/assets/img");

        if (!$fullPath || !$basePath || !str_starts_with($fullPath, $basePath) || !is_file($fullPath)) {
            throw new FileNotFoundException($path);
        }

        return new BinaryFileResponse($fullPath);
    }
}
