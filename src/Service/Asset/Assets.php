<?php

namespace App\Service\Asset;

use Symfony\Component\Asset\Exception\AssetNotFoundException;
use Symfony\Component\Asset\Packages;

/**
 * Wrapper for the Symfony's Packages service
 * Supports strict mode packages without exceptions
 */
class Assets
{
    public function __construct(
        private Packages $packages,
        private string $defaultPackage = 'strict',
    ) {
    }

	/**
	 * Gets the URL for an asset, returning an unversioned path if the asset is not found.
	 * If using a strict_mode package, this method can throw an AssetNotFoundException.
	 */
    public function getUrl(string $path, ?string $packageName = null): string
    {
		return $this->packages->getUrl($path, $packageName);
    }

	/**
	 * Gets the URL for an asset, returning null if the asset is not found.
	 */
    public function getAsset(string $path, ?string $packageName = null): ?string
    {
        $packageName = $packageName ?? $this->defaultPackage;

        try {
            return $this->packages->getUrl($path, $packageName);
        } catch (AssetNotFoundException) {
            return null;
        }
    }

	/**
	 * Gets the URL for the first available asset in the given list of paths, or null if none are found.
	 */
	public function getAvailableAsset(...$paths): ?string {
		foreach ($paths as $path) {
			$url = $this->getAsset($path);
			if ($url !== null) {
				return $url;
			}
		}
		return null;
	}
}
