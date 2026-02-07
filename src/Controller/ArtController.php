<?php

namespace App\Controller;

use App\Entity\RolePlayText;
use App\Entity\RolePlayTextPage;
use SplFileInfo;
use Symfony\Component\Asset\Packages;
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
            ->filter(fn(SplFileInfo $f) => in_array(strtolower($f->getExtension()), ['png','jpg','jpeg','gif','svg','webp']))
            ->sortByName(true);

        $assets = [];
        foreach ($finder as $file) {

            $relative = $file->getRelativePath();
            $top = explode(DIRECTORY_SEPARATOR, $relative)[0] ?: '[ root ]';

            $assets[$top][] = $relative ? "$relative/{$file->getFilename()}": $file->getFilename();
        }

        return $this->render('ajax/art/assets.html.twig', $this->addDefaultTwigArgs(null, [
            'assets' => $assets,
        ]));
    }

    #[Route(path: 'jx/art/rp-texts', name: 'art_rp_texts', condition: 'request.isXmlHttpRequest()')]
    public function rpTexts(Packages $assetPackages): Response
    {
        if ($resp = $this->denyUnlessArt()) {
            return $resp;
        }

        $rpTexts = $this->entity_manager->getRepository(RolePlayText::class)->findAll();
        
        // Process RP texts to convert {asset} tags to actual image URLs
        $processedRpTexts = [];
        foreach ($rpTexts as $rp) {
            $processedPages = [];
            foreach ($rp->getPages() as $page) {
                $content = $page->getContent();
                // Replace {asset}path{endasset} with <img src="path" />
                $content = preg_replace_callback(
                    '/{asset}([a-zA-Z0-9.\/_-]+){endasset}/',
                    function($matches) use ($assetPackages) {
                        return "<img src='" . $assetPackages->getUrl($matches[1]) . "' alt='' style='max-width: 100%;' />";
                    },
                    $content
                );
                $processedPages[] = $content;
            }
            $processedRpTexts[] = [
                'rp' => $rp,
                'processedPages' => $processedPages,
            ];
        }

        return $this->render('ajax/art/rp_texts.html.twig', $this->addDefaultTwigArgs(null, [
            'rpTexts' => $processedRpTexts,
        ]));
    }
}
