<?php

namespace App\Service\Actions\User;

use App\Entity\Award;
use App\Entity\Emotes;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;

readonly class GetUnlockedEmotesAction
{
    public function __construct(
        private EntityManagerInterface $em,
        private Packages $asset,
    ) { }

    /**
     * Returns all emotes a user can use
     * @param User $user
     * @param ?string $lang
     * @return int[]
     */
    public function __invoke(User $user, ?string $lang = null): array
    {
        $lang ??= $user->getLanguage();

        $repo = $this->em->getRepository(Emotes::class);
        $emotes = $repo->getDefaultEmotes();
        $awards = $this->em->getRepository(Award::class)->getAwardsByUser($user);

        foreach ($awards as $entry) {
            /** @var Award $entry */
            if (!$entry->getPrototype() || $entry->getPrototype()->getAssociatedTag() === null) {
                continue;
            }
            $emote = $repo->findByTag($entry->getPrototype()->getAssociatedTag());
            if ($emote && !in_array($emote, $emotes, true)) {
                $emotes[] = $emote;
            }
        }

        $results = [];
        foreach ($emotes as $entry) {
            /** @var Emotes $entry */
            if ($entry === null) {
                continue;
            }
            $path = $entry->getPath();
            if ($entry->getI18n()) {
                $path = str_replace('{lang}', $lang, $path);
            }
            $results[$entry->getTag()] = $this->asset->getUrl($path);
        }

        return $results;
    }
}
