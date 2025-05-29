<?php

namespace App\Controller\REST\User\Soul;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Entity\Award;
use App\Entity\CitizenProfession;
use App\Entity\Emotes;
use App\Entity\ForumModerationSnippet;
use App\Entity\ItemPrototype;
use App\Entity\User;
use App\Enum\UserSetting;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/rest/v1/user/soul/editor', name: 'rest_user_soul_editor_', condition: "request.headers.get('Accept') === 'application/json'")]
#[GateKeeperProfile(allow_during_attack: true)]
#[IsGranted('ROLE_USER')]
class EditorController extends CustomAbstractCoreController
{

    /**
     * @param Packages $assets
     * @return JsonResponse
     */
    #[Route(path: '', name: 'base', methods: ['GET'])]
    public function index(Packages $assets): JsonResponse {
        return new JsonResponse([
            'header' => [
                'title' => $this->translator->trans('Titel', [], 'global'),
                'tag' => $this->translator->trans('Tag', [], 'forum'),
                'add_tag' => $this->translator->trans('Tag hinzufügen (optional)', [], 'global'),
                'no_tag' => $this->translator->trans('Kein Tag', [], 'global'),
                'version' => $this->translator->trans('Version', [], 'global'),
                'language' => $this->translator->trans('Sprache', [], 'global'),
            ],
            'sections' => [
                'preview' => $this->translator->trans('Vorschau', [], 'global'),
                'message' => $this->translator->trans('Deine Nachricht', [], 'global'),
            ],
            'common' => [
                'insert' => $this->translator->trans('Einfügen', [], 'global'),
                'abort' => $this->translator->trans('Abbrechen', [], 'global'),
                'ctrl' => $this->translator->trans('STRG', [], 'global'),
                'enter' => $this->translator->trans('ENTER', [], 'global'),
                'send' => $this->translator->trans('Absenden', [], 'global'),
                'help' => $this->translator->trans('Hilfe', [], 'global'),
                'expand' => $this->translator->trans('Zum erweiterten Editor wechseln', [], 'global'),
            ],
            'controls' => [
                    'b' => $this->translator->trans('Fett', [], 'global'),
                    'i' => $this->translator->trans('Kursiv', [], 'global'),
                    'u' => $this->translator->trans('Unterstreichen', [], 'global'),
                    's' => $this->translator->trans('Durchstreichen', [], 'global'),
                    'c' => $this->translator->trans('Keine Formatierung', [], 'global'),
                    'big' => $this->translator->trans('Groß', [], 'global'),
                    'bad' => $this->translator->trans('Verräter', [], 'global'),
                    'link' => $this->translator->trans('Link einfügen', [], 'global'),
                    'link-url' => $this->translator->trans('Link-URL', [], 'global'),
                    'link-text' => $this->translator->trans('Link-Text', [], 'global'),
                    'image' => $this->translator->trans('Bild einfügen', [], 'global'),
                    'image-url' => $this->translator->trans('Bild-URL', [], 'global'),
                    'image-text' => $this->translator->trans('Bildtitel', [], 'global'),
                    'admannounce' => $this->translator->trans('Admin Ankündigung', [], 'global'),
                    'modannounce' => $this->translator->trans('Mod Ankündigung', [], 'global'),
                    'announce' => $this->translator->trans('Orakel Ankündigung', [], 'global'),
                    '*' => $this->translator->trans('Listenpunkt', [], 'global'),
                    '0' => $this->translator->trans('Num. Listenpunkt', [], 'global'),
                    'quote' => $this->translator->trans('Zitat', [], 'global'),
                    'quote-dialog' => $this->translator->trans('Spieler auswählen', [], 'global'),
                    'quote-placeholder' => $this->translator->trans('Gib den Namen des Spielers ein', [], 'global'),
                    'spoiler' => $this->translator->trans('Spoiler', [], 'global'),
                    'aparte' => $this->translator->trans('Vertraulich', [], 'global'),
                    'glory' => $this->translator->trans('Ruhm', [], 'global'),
                    'code' => $this->translator->trans('Code', [], 'global'),
                    'hr' => $this->translator->trans('Linie', [], 'global'),
                    'rp' => $this->translator->trans('Rollenspiel', [], 'global'),
                    'rp-dialog' => $this->translator->trans('Spieler auswählen', [], 'global'),
                    'rp-placeholder' => $this->translator->trans('Gib den Namen des Spielers ein', [], 'global'),
                    'collapse' => $this->translator->trans('Einklappen', [], 'global'),
                    'collapse-dialog' => $this->translator->trans('Eingeklappte Sektion hinzufügen', [], 'global'),
                    'collapse-placeholder' => $this->translator->trans('Kopfzeile', [], 'global'),
                    'collapse-language' => $this->translator->trans('Sprache', [], 'global'),
                    'collapse-language-help' => $this->translator->trans('Wähle hier eine Sprache aus, um diese Sektion für Spieler mit dieser Sprache automatisch auszuklappen.', [], 'global'),
                    'collapse-language-none' => $this->translator->trans('Keine Sprache', [], 'global'),
                    'collapse-languages' => [
                        'de' => $this->translator->trans('Deutsch', [], 'global'),
                        'en' => $this->translator->trans('Englisch', [], 'global'),
                        'fr' => $this->translator->trans('Französisch', [], 'global'),
                        'es' => $this->translator->trans('Spanisch', [], 'global'),
                    ],
                    'poll' => $this->translator->trans('Umfrage einfügen', [], 'global'),
                    'poll-help' => $this->translator->trans('Kontrolliere deine Eingaben! Umfragen können nach dem Absenden des Posts nicht mehr bearbeitet werden!', [], 'global'),
                    'poll-question' => $this->translator->trans('Fragetext eingeben (optional)', [], 'global'),
                    'poll-answer' => $this->translator->trans('Antwort', [], 'global'),
                    'poll-answer-add' => $this->translator->trans('Antwort hinzufügen', [], 'global'),
                    'poll-info' => $this->translator->trans('Info-Text', [], 'global'),
                    'poll-info-add' => $this->translator->trans('Info-Text hinzufügen', [], 'global'),
                    'poll-optional' => $this->translator->trans('(optional)', [], 'global'),
                    'poll-need-answer' => $this->translator->trans('Du musst mindestens eine Antwort eingeben.', [], 'global'),
                    '@' => $this->translator->trans('Spielernamen einfügen', [], 'global'),
                    '@-dialog' => $this->translator->trans('Spieler auswählen', [], 'global'),
                    '@-placeholder' => $this->translator->trans('Gib den Namen des Spielers ein', [], 'global'),

                    'emotes_img' => $assets->getUrl('build/images/forum/smile.gif'),
                    'default_img' => $assets->getUrl('build/images/emotes/middot.gif'),
                    'ressources_img' => $assets->getUrl('build/images/item/item_wood2.gif'),
                    'games_img' => $assets->getUrl('build/images/item/item_dice.gif'),
                    'rp_img' => $assets->getUrl('build/images/forum/rp.png'),
                    'mod_img' => $assets->getUrl('build/images/icons/mod.png'),
                    'help_img' => $assets->getUrl('build/images/icons/small_help.gif'),
                    'answer_img' => $assets->getUrl('build/images/forum/selected.png'),
                    'info_img' => $assets->getUrl('build/images/icons/small_talk.gif'),
                ]
        ]);
    }

    /**
     * @param int|null $id
     * @param User|null $user
     * @param EntityManagerInterface $em
     * @param Packages $assets
     * @param RoleHierarchyInterface $roles
     * @param TagAwareCacheInterface $gameCachePool
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route(path: '/me/unlocks/{context}/emotes', name: 'list_emotes_me', methods: ['GET'])]
    #[Route(path: '/{id}/unlocks/{context}/emotes', name: 'list_emotes', methods: ['GET'])]
    public function list_emotes(
        ?int $id,
        string $context,
        ?User $user,
        EntityManagerInterface $em,
        Packages $assets,
        RoleHierarchyInterface $roles,
        TagAwareCacheInterface $gameCachePool
    ): JsonResponse {
        if ($id === null) $user = $this->getUser();
        elseif ($user !== $this->getUser()) return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $unlock_all = in_array($context, [
            'announcement', 'changelog'
        ]) && $this->isGranted('ROLE_ELEVATED');
        $unlock_s = $unlock_all ? 'all' : 'default';

        $emotes = $gameCachePool->get("mh_app_unlocks_emotes_{$user->getId()}_{$user->getLanguage()}_{$unlock_s}", function (ItemInterface $item) use ($user, $em, $unlock_all) {

            $item->expiresAfter(360)->tag(["user-{$user->getId()}-emote-unlocks",'emote-unlocks']);

            $repo = $em->getRepository(Emotes::class);
            $emotes = $unlock_all ? $repo->findAll() : $repo->getDefaultEmotes();

            $awards = $unlock_all ? [] : $em->getRepository(Award::class)->getAwardsByUser($user);

            foreach($awards as $entry) {
                /** @var $entry Award */
                if (!$entry->getPrototype() || $entry->getPrototype()->getAssociatedTag() === null) continue;
                $emote = $repo->findByTag($entry->getPrototype()->getAssociatedTag());
                if(!in_array($emote, $emotes)) {
                    $emotes[] = $emote;
                }
            }

            $data = [];
            foreach($emotes as $entry) {
                /** @var $entry Emotes */
                if ($entry === null) continue;
                $data[$entry->getTag()] = [
                    'tag' => $entry->getTag(),
                    'path' => $entry->getI18n() ? str_replace('{lang}', $user->getLanguage() ?? 'de', $entry->getPath()) : $entry->getPath(),
                    'orderIndex' => $entry->getOrderIndex(),
                    'groups' => $user?->getSetting(UserSetting::DisableEmoteCategories) ? ['emotes'] : $entry->getGroups(),
                ];
            }

            return $data;
        });

        $snippets = [];
        if ($this->isGranted('ROLE_ELEVATED') || $this->isGranted('ROLE_ANIMAC') || $this->isGranted('ROLE_ART')) {
            $entities = $em->getRepository(ForumModerationSnippet::class)->findBy( ['role' => [...$roles->getReachableRoleNames( $user->getRoles() ), '*']] );
            foreach ($entities as $snippet) {
                $key = $snippet->getLang() === $user->getLanguage() ? "%%{$snippet->getShort()}" : "%{$snippet->getLang()}%{$snippet->getShort()}";
                $snippets[$key] = [
                    'lang' => $snippet->getLang(),
                    'key' => $key,
                    'value' => $snippet->getText(),
                    'role' => str_replace('ROLE_', '', $snippet->getRole()),
                ];
            }
        }

        return new JsonResponse([
            'result' => array_map(fn(array $a) => array_merge($a, ['url' => $assets->getUrl( $a['path'] )]), $emotes),
            'mock' => array_filter([
                ':)' => ':smile:',
                '=)' => ':smile:',
                '🙂' => ':smile:',
                '😊' => ':smile:',
                '🙃' => ':smile:',
                '🫠' => ':smile:',
                '😇' => ':smile:',
                '🥲' => ':smile:',
                '😏' => ':smile:',
                '😌' => ':smile:',
                '🤤' => ':smile:',
                '🤠' => ':smile:',
                '😎' => ':smile:',
                '🤓' => ':smile:',
                '😗' => ':smile:',
                '☺' => ':smile:',
                '😚' => ':smile:',
                '😙' => ':smile:',
                '😽' => ':smile:',
                '👶' => ':smile:',
                '🧒' => ':smile:',
                '👦' => ':smile:',
                '👧' => ':smile:',
                '🧑' => ':smile:',
                '👩' => ':smile:',
                '🧒' => ':smile:',

                ':d' => ':lol:',
                '=d' => ':lol:',
                '😀' => ':lol:',
                '😃' => ':lol:',
                '😄' => ':lol:',
                '😁' => ':lol:',
                '😆' => ':lol:',
                '😅' => ':lol:',
                '🤣' => ':lol:',
                '😂' => ':lol:',
                '😸' => ':lol:',
                '😹' => ':lol:',
                '😺' => ':lol:',

                ':(' => ':sad:',
                '=(' => ':sad:',
                '😒' => ':sad:',
                '😔' => ':sad:',
                '😟' => ':sad:',
                '🙁' => ':sad:',
                '☹' => ':sad:',
                '😕' => ':sad:',
                '🫤' => ':sad:',
                '😦' => ':sad:',
                '😥' => ':sad:',

                ';)' => ':blink:',
                '😉' => ':blink:',
                '😘' => ':blink:',
                '😜' => ':blink:',

                ':o' => ':surprise:',
                '=o' => ':surprise:',
                '😮' => ':surprise:',
                '😯' => ':surprise:',
                '😲' => ':surprise:',

                '🤔' => ':thinking:',
                '🧐' => ':thinking:',

                '😨' => ':horror:',
                '😰' => ':horror:',
                '😱' => ':horror:',
                '🙀' => ':horror:',

                '😷' => ':sick:',
                '🤒' => ':sick:',
                '🤕' => ':sick:',
                '🤢' => ':sick:',
                '🤮' => ':sick:',
                '🤧' => ':sick:',
                '🥴' => ':sick:',

                '🤐' => ':neutral:',
                '🤨' => ':neutral:',
                '😐' => ':neutral:',
                '😑' => ':neutral:',
                '😶' => ':neutral:',
                '🫥' => ':neutral:',

                '💀' => ':death:',
                '☠' => ':death:',

                '💤' => ':sleep:',
                '😪' => ':sleep:',
                '😴' => ':sleep:',

                '😡' => ':rage:',
                '😠' => ':rage:',
                '🤬' => ':rage:',

                '=>' => ':arrowright:',
            ], fn(string $v) => array_key_exists( $v, $emotes )),
            'snippets' => empty($snippets) ? null : [
                'base' => $user->getLanguage(),
                'list' => $snippets,
            ],
        ]);
    }

    /**
     * @param int|null $id
     * @param User|null $user
     * @param Packages $assets
     * @return JsonResponse
     */
    #[Route(path: '/me/unlocks/{context}/games', name: 'list_games_me', methods: ['GET'])]
    #[Route(path: '/{id}/unlocks/{context}/games', name: 'list_games', methods: ['GET'])]
    public function list_games(
        ?int $id,
        ?User $user,
        Packages $assets
    ): JsonResponse {
        if ($id === null) $user = $this->getUser();
        elseif ($user !== $this->getUser()) return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $data = [
            'd4' => 'dice4',
            'd6' => 'dice6',
            'd8' => 'dice8',
            'd10' => 'dice10',
            'd12' => 'dice12',
            'd20' => 'dice20',
            'd100' => 'dice100',
            'letter' => 'lta',
            'consonant' => 'ltc',
            'vowel' => 'ltv',
            'rps' => 'rps',
            'coin' => 'coin',
            'card' => 'card',
            ...($user->getActiveCitizen()?->getAlive() ? [
                'town' => 'town',
                'coords' => 'coords'
            ] : [])
        ];

        return new JsonResponse([
            'result' => array_map( fn(string $k, string $v, int $o) => [
                'tag' => '{' . $k . '}',
                'path' => "build/images/forum/{$v}.png",
                'url' => $assets->getUrl( "build/images/forum/{$v}.png" ),
                'orderIndex' => $o,
            ], array_keys($data), array_values($data), array_keys(array_values($data)) )
        ]);

    }

    /**
     * @param int|null $id
     * @param User|null $user
     * @param Packages $assets
     * @return JsonResponse
     */
    #[Route(path: '/me/unlocks/{context}/ressources', name: 'list_ressources_me', methods: ['GET'])]
    #[Route(path: '/{id}/unlocks/{context}/ressources', name: 'list_ressources', methods: ['GET'])]
    public function list_ressources(
        ?int $id,
        ?User $user,
        Packages $assets,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($id === null) $user = $this->getUser();
        elseif ($user !== $this->getUser()) return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $items = $em->getRepository(ItemPrototype::class)->findBy(['emote' => true]);

        return new JsonResponse([
            'result' => array_combine(array_map(fn(ItemPrototype $p) => ":item_{$p->getIcon()}:", $items), array_map( fn(ItemPrototype $p) => [
                'tag' => ":item_{$p->getIcon()}:",
                'path' => "build/images/item/item_{$p->getIcon()}.gif",
                'url' => $assets->getUrl( "build/images/item/item_{$p->getIcon()}.gif" ),
                'orderIndex' => ($p->getSort() ?? 0) * 1000 + $p->getId(),
            ], $items ))
        ]);

    }

    /**
     * @param int|null $id
     * @param string $context
     * @param User|null $user
     * @param EntityManagerInterface $em
     * @param Packages $assets
     * @param TranslatorInterface $trans
     * @return JsonResponse
     */
    #[Route(path: '/me/unlocks/{context}/rp', name: 'list_rp_me', methods: ['GET'])]
    #[Route(path: '/{id}/unlocks/{context}/rp', name: 'list_rp', methods: ['GET'])]
    public function list_rp(
        ?int $id,
        string $context,
        ?User $user,
        EntityManagerInterface $em,
        Packages $assets,
        TranslatorInterface $trans,
    ): JsonResponse {
        if ($id === null) $user = $this->getUser();
        elseif ($user !== $this->getUser()) return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $professions = $em->getRepository(CitizenProfession::class)->findAll();

        $profession_map = [
            'collec' => 'scav',
            'guardian' => 'guard',
            'hunter' => 'scout',
            'survivalist' => 'surv',
            'shaman' => 'sham',
        ];

        $result = [];
        foreach ($professions as $profession)
            $result[] = [
                'tag' => '{citizen,' . ($profession_map[$profession->getName()] ?? $profession->getName()) . '}',
                'path' => "/build/images/professions/{$profession->getIcon()}.gif",
                'url' => $assets->getUrl( "build/images/professions/{$profession->getIcon()}.gif" ),
                'orderIndex' => $profession->getName() === 'none' ? 9999 : (100 + $profession->getId())
            ];

        $data = [
            '' => 'icons/small_human',
            'hero' => 'professions/hero',
            'dead' => 'professions/death',
            'shunned' => 'icons/banished',
        ];

        if ($context === 'logChat') $data['zone'] = 'icons/item_map';

        return new JsonResponse([
            'result' => array_merge($result, array_map( fn(string $k, string $v, int $o) => [
                'tag' => $k === '' ? '{citizen}' :  "{citizen,$k}",
                'path' => "build/images/{$v}.gif",
                'url' => $assets->getUrl( "build/images/{$v}.gif" ),
                'orderIndex' => $o
            ], array_keys($data), array_values($data), array_keys(array_values($data)) )),
            'help' => <<<HELP
                <h1>{$trans->trans('Zufälliger Bürger', [], 'game')}</h1>
                <em>{$trans->trans('Hiermit kannst du den Namen eines zufälligen Bürgers in deinen Post anfügen. Bei Bedarf kannst du die Auswahl auf bestimmte Berufe, Helden oder bereits gestorbene Bürger eingrenzen.', [], 'game')}</em>
                <em>
                    {$trans->trans('Wenn zu den gleichen zufällig gewählten Bürger mehrfach in deinem Text verwenden willst, kannst du ihm eine Nummer zuweisen, beispielsweise:', [], 'game')}
                    <code>{citizen,tamer,1}</code>
                </em>
            HELP,
        ]);

    }

    /**
     * @param int|null $id
     * @param string $context
     * @param User|null $user
     * @param EntityManagerInterface $em
     * @param Packages $assets
     * @param TranslatorInterface $trans
     * @return JsonResponse
     */
    #[Route(path: '/me/unlocks/shoutbox/rp', name: 'list_rp_coa_me', methods: ['GET'], priority: 1)]
    #[Route(path: '/{id}/unlocks/shoutbox/rp', name: 'list_coa_rp', methods: ['GET'], priority: 1)]
    public function list_rp_coa(
        ?int $id,
        ?User $user,
        Packages $assets,
        TranslatorInterface $trans,
    ): JsonResponse {
        if ($id === null) $user = $this->getUser();
        elseif ($user !== $this->getUser()) return new JsonResponse([], Response::HTTP_FORBIDDEN);

        return new JsonResponse([
                                    'result' => [[
                                        'tag' => '{coalition}',
                                        'path' => "build/images/icons/small_human.gif",
                                        'url' => $assets->getUrl( "build/images/icons/small_human.gif" ),
                                        'orderIndex' => 0
                                    ]],
                                    'help' => <<<HELP
                <h1>{$trans->trans('Zufälliges Koalitionsmitglied', [], 'game')}</h1>
                <em>{$trans->trans('Hiermit kannst du den Namen eines zufälligen Mitglieds deiner Koalition in deinen Post anfügen.', [], 'game')}</em>
                <em>
                    {$trans->trans('Wenn zu den gleichen zufällig gewählten Bürger mehrfach in deinem Text verwenden willst, kannst du ihm eine Nummer zuweisen, beispielsweise:', [], 'game')}
                    <code>{coalition,1}</code>
                </em>
            HELP,
                                ]);

    }
}
