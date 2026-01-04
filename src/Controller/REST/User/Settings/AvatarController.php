<?php

namespace App\Controller\REST\User\Settings;

use App\Entity\AccountRestriction;
use App\Entity\Media;
use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\Actions\Cache\InvalidateTagsInAllPoolsAction;
use App\Service\Actions\User\RecalculateMediaExpirationAction;
use App\Service\ConfMaster;
use App\Service\JSONRequestParser;
use App\Service\Media\MediaService;
use App\Service\PermissionHandler;
use App\Service\UserHandler;
use App\Structures\Media\AnonymousMediaVariant;
use App\Structures\Media\MediaConversion;
use ArrayHelpers\Arr;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * @method User getUser()
 */
#[Route(path: '/rest/v1/user/settings/avatar', name: 'rest_user_settings_avatar_', condition: "request.headers.get('Accept') === 'application/json'")]
#[IsGranted('ROLE_USER')]
class AvatarController extends AbstractController
{

    /**
     * @param Packages $assets
     * @param TranslatorInterface $trans
     * @return JsonResponse
     */
    #[Route(path: '', name: 'base', methods: ['GET'])]
    #[Route(path: '/index', name: 'base_index', methods: ['GET'])]
    public function index(Packages $assets, TranslatorInterface $trans): JsonResponse {
        return new JsonResponse([
            'strings' => [
                'common' => [
                    'help' => $trans->trans('Hilfe', [], 'global'),

                    'no_avatar' => $trans->trans('Damit andere Spieler dich besser erkennen, kannst du hier ein Profilbild hochladen', [], 'soul'),

                    'view_current' => $trans->trans('Aktuelles Profilbild', [], 'soul'),
                    'view_pending' => $trans->trans('Vorschau des neuen Profilbilds', [], 'soul'),
                    'view_history' => $trans->trans('Frühere Profilbilder', [], 'soul'),
                    'view_check' => $assets->getUrl('build/images/icons/done.png'),
                    'view_warn' => $assets->getUrl('build/images/icons/warning_anim.gif'),

                    'edit_help' => $trans->trans('MyHordes verwendet drei unterschiedliche Bildformate für dein Profilbild. Standartmäßig werden diese automatisch aus deinem ausgewählten Bild ermittelt. Du kannst die Bildausschnitte aber auch selbst wählen, wenn du möchtest. Klicke dazu einfach bei dem entsprechenden Bildformat auf "Bearbeiten".', [], 'soul'),
                    'edit_help2' => $trans->trans('Bist du fertig, klicke auf "Profilbild speichern" um deinen Avatar hochzuladen. Dein Bild wird dann vom System automatisch komprimiert und zugeschnitten.', [], 'soul'),
                    'edit_help3' => $trans->trans('Das automatische Komprimieren und Zuschneiden kann einige Sekunden dauern. Sobald dein Bild fertig verarbeitet wurde, siehst du noch einmal eine Vorschau und kannst das Bild dann final als deinen neuen Avatar festlegen.', [], 'soul'),

                    'edit_aspect' => $trans->trans('Seitenverhältnis', [], 'soul'),
                    'edit_aspect_free' => $trans->trans('Beliebig', [], 'soul'),
                    'edit_aspect_original' => $trans->trans('Wie Originalbild', [], 'soul'),

                    'preview_help' => $trans->trans('Sieht alles gut aus?', [], 'soul'),
                    'preview_help2' => $trans->trans('Dein neues Profilbild ist noch nicht aktiviert.', [], 'soul'),
                    'preview_help3' => $trans->trans('Klicke unten auf "Profilbild aktivieren", um zu deinem neuen Avatar zu wechseln.', [], 'soul'),
                    'preview_help4' => $trans->trans('Dein altes Profilbilds wird noch für eine Weile gespeichert, sodass du für eine begrenzte Zeit einfach zurückwechseln kannst, ohne dein altes Profilbild erneut hochladen zu müssen.', [], 'soul'),
                    'preview_help5' => $trans->trans('Dein neues Profilbild wird gerade verarbeitet. Bitte hab einen Augenblick Geduld...', [], 'soul'),

                    'format_upload' => $trans->trans('Originales Bild', [], 'soul'),
                    'format_default' => $trans->trans('Normale Anzeige', [], 'soul'),
                    'format_round' => $trans->trans('Runde Anzeige', [], 'soul'),
                    'format_small' => $trans->trans('Komprimierte Anzeige', [], 'soul'),

                    'pending' => $trans->trans('Wird berechnet...', [], 'soul'),
                    'missing' => $trans->trans('Format konnte nicht berechnet werden', [], 'soul'),
                    'info' => $trans->trans('{x} × {y} Pixel, {size}', [], 'soul'),
                    'dimensions' => $trans->trans('Größe {x} × {y} Pixel', [], 'soul'),
                    'none' => $trans->trans('Nicht hochgeladen', [], 'soul'),
                    'fallback' => $trans->trans('Automatisch erzeugt', [], 'soul'),

                    'action_delete' => $trans->trans('Profilbild löschen', [], 'soul'),
                    'action_delete_preview' => $trans->trans('Verwerfen', [], 'soul'),
                    'action_activate_preview' => $trans->trans('Profilbild aktivieren', [], 'soul'),
                    'action_activate_history' => $trans->trans('Dieses Profilbild verwenden', [], 'soul'),
                    'action_edit' => $trans->trans('Neues Profilbild hochladen', [], 'soul'),
                    'action_modify' => $trans->trans('Profilbild bearbeiten', [], 'soul'),
                    'action_create' => $trans->trans('Profilbild hochladen', [], 'soul'),
                    'action_cancel' => $trans->trans('Abbrechen', [], 'global'),
                    'action_upload' => $trans->trans('Profilbild speichern', [], 'soul'),

                    'action_icon_delete' => $assets->getUrl('build/images/icons/small_trash_red.png'),
                    'action_icon_restore' => $assets->getUrl('build/images/icons/star.gif'),

                    'confirm' => $trans->trans('Bestätigen?', [], 'global'),
                    'success_create' => $trans->trans('Dein Profilbild wurde erfolgreich aktualisiert.', [], 'global'),
                    'success_delete' => $trans->trans('Das gewählte Profilbild wurde erfolgreich gelöscht.', [], 'global'),

                    'error_single_file' => $trans->trans('Bitte wähle nur eine einzige Datei aus.', [], 'soul'),
                    'error_too_large' => $trans->trans('Die Datei ist zu groß.', [], 'soul'),
                    'error_unknown_format' => $trans->trans('Dieses Dateiformat wird nicht unterstützt.', [], 'soul'),

                    'will_delete_in' => $trans->trans('Dieses Profilbild wird in ungefähr {days} Tagen automatisch gelöscht.', [], 'soul'),
                    'will_delete' => $trans->trans('Dieses Profilbild wird in Kürze automatisch gelöscht.', [], 'soul'),

                    'edit_redo' => $trans->trans('Anderes Bild auswählen', [], 'soul'),
                    'edit_auto' => $trans->trans('Komprimierten Ausschnitt automatisch festlegen', [], 'soul'),
                    'edit_manual' => $trans->trans('Ich möchte den komprimierten Ausschnitt selbst festlegen', [], 'soul'),
                    'edit_now' => $trans->trans('Bearbeiten', [], 'soul'),
                    'edit_finish' => $trans->trans('Fertig', [], 'soul'),

                    'compression' => $trans->trans('Bildformat', [], 'soul'),
                    'compression_help'   => $trans->trans('Die empfohlene Einstellung erzeugt in den allermeisten Fällen ein Bild in bestmöglicher Qualität. In seltenen Fällen, insbesondere bei sehr dunklen Bildern, kann das Ergebnis jedoch verwaschen aussehen. Versuche in diesem Fall, die alternative Option auszuwählen.', [], 'soul'),
                    'compression_avif'   => $trans->trans('Empfohlen (bevorzugt AV1)', [], 'soul'),
                    'compression_webp'   => $trans->trans('Alternativ (bevorzugt WebP)', [], 'soul'),
                    'compression_noloss' => $trans->trans('Verlustlos (bevorzugt WebP, empfehlenswert für Pixel Art)', [], 'soul'),
                ],

                'formats' => [
                    'default-still'         => $trans->trans('Normale Anzeige', [], 'soul'),
                    'default-animated'      => $trans->trans('Normale Anzeige', [], 'soul') . ' (' . $trans->trans('animiert', [], 'soul') . ')',
                    'default-hd-still'      => $trans->trans('Normale Anzeige', [], 'soul') . ' / ' . $trans->trans('hohe Auflösung', [], 'soul'),
                    'default-hd-animated'   => $trans->trans('Normale Anzeige', [], 'soul') . ' (' . $trans->trans('animiert', [], 'soul') . ')' . ' / ' . $trans->trans('hohe Auflösung', [], 'soul'),

                    'bubble-small-still'    => $trans->trans('Runde Anzeige', [], 'soul') . ' / ' . $trans->trans('reduzierte Auflösung', [], 'soul'),
                    'bubble-small-animated' => $trans->trans('Runde Anzeige', [], 'soul') . ' (' . $trans->trans('animiert', [], 'soul') . ')' . ' / ' . $trans->trans('reduzierte Auflösung', [], 'soul'),
                    'bubble-still'          => $trans->trans('Runde Anzeige', [], 'soul'),
                    'bubble-animated'       => $trans->trans('Runde Anzeige', [], 'soul') . ' (' . $trans->trans('animiert', [], 'soul') . ')',
                    'bubble-hd-still'       => $trans->trans('Runde Anzeige', [], 'soul') . ' / ' . $trans->trans('hohe Auflösung', [], 'soul'),
                    'bubble-hd-animated'    => $trans->trans('Runde Anzeige', [], 'soul') . ' (' . $trans->trans('animiert', [], 'soul') . ')' . ' / ' . $trans->trans('hohe Auflösung', [], 'soul'),

                    'letterbox-still'           => $trans->trans('Komprimierte Anzeige', [], 'soul'),
                    'letterbox-animated'        => $trans->trans('Komprimierte Anzeige', [], 'soul') . ' (' . $trans->trans('animiert', [], 'soul') . ')',
                    'letterbox-hd-still'        => $trans->trans('Komprimierte Anzeige', [], 'soul') . ' / ' . $trans->trans('hohe Auflösung', [], 'soul'),
                    'letterbox-hd-animated'     => $trans->trans('Komprimierte Anzeige', [], 'soul') . ' (' . $trans->trans('animiert', [], 'soul') . ')' . ' / ' . $trans->trans('hohe Auflösung', [], 'soul'),

                    'legacy-default'         => $trans->trans('Normale Anzeige', [], 'soul') . ' (' . $trans->trans('importiert', [], 'soul') . ')',
                    'legacy-default-hd'      => $trans->trans('Normale Anzeige', [], 'soul') . ' (' . $trans->trans('importiert', [], 'soul') . ')' . ' / ' . $trans->trans('hohe Auflösung', [], 'soul'),
                    'legacy-classic'         => $trans->trans('Komprimierte Anzeige', [], 'soul') . ' (' . $trans->trans('importiert', [], 'soul') . ')',
                    'legacy-classic-hd'      => $trans->trans('Komprimierte Anzeige', [], 'soul') . ' (' . $trans->trans('importiert', [], 'soul') . ')' . ' / ' . $trans->trans('hohe Auflösung', [], 'soul'),
                ]
            ]
        ]);
    }

    private function renderAvatarConversion( MediaConversion $conversion, ?string $id = null ): array {
        return [
            'id' => $id ?? $conversion->conversion,
            'url' => $conversion->url,
            'format' => MediaService::mimeTypeToExtension( $conversion->mime, false ),
            'size' => $conversion->size,
            'x' => $conversion->width,
            'y' => $conversion->height,
            'f' => $conversion->frames,
        ];
    }

    private function renderAvatar(?Media $media, string $tag, bool $include_pending = false): ?array {
        $conversion = $media?->getLargestConversionByTag( $tag, include_pending: $include_pending );
        if (!$conversion) return null;

        return [
            ...$this->renderAvatarConversion( $conversion, $media->getId() ),
            'conversions' => array_map(
                fn(MediaConversion $c) => $this->renderAvatarConversion( $c ),
                $media?->getConversionsByTag( $tag, $include_pending ),
            )
        ];
    }

    /**
     * @param MediaService $mediaService
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/media', name: 'list', methods: ['GET'])]
    public function fetchMedia(MediaService $mediaService): JsonResponse {
        $render = function(?Media $media, bool $include_pending = false, bool $include_source = false, bool $include_expiration = false): ?array {
            $data = $media ? array_filter([
                'id' => $media->getId(),
                ...( $include_source ? [
                    'source' => true,
                ] : []),
                ...( $include_expiration && $media->getDeleteAt() !== null ? [
                    'expires' => max(0,-(int)floor(Carbon::parse( $media->getDeleteAt() )->diffInDays( Carbon::now() ))),
                ] : []),
                'default'   => $this->renderAvatar($media, 'square', $include_pending),
                'round'     => $this->renderAvatar($media, 'circular', $include_pending),
                'small'     => $this->renderAvatar($media, 'classic', $include_pending),
            ], fn(mixed $v) => $v !== null) : null;

            return empty($data) ? null : $data;
        };

        return new JsonResponse( array_filter([
            'avatar'  => $render( $mediaService->getSingleMediaForObject( $this->getUser(), 'avatar' ), include_source: true ),
            'pending' => $render( $mediaService->getSingleMediaForObject( $this->getUser(), 'avatar-pending' ), true ),
            'history' => $mediaService->getMediaForObject( $this->getUser(), 'avatar-history' )->map( fn(Media $m) => $render( $m, include_expiration: true ) )->toArray(),
        ]));
    }

    /**
     * @param EntityManagerInterface $em
     * @param InvalidateTagsInAllPoolsAction $clearCache
     * @param MediaService $mediaService
     * @param RecalculateMediaExpirationAction $expirationAction
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/media', name: 'delete', methods: ['DELETE'])]
    public function deleteMedia(EntityManagerInterface $em, InvalidateTagsInAllPoolsAction $clearCache, MediaService $mediaService, RecalculateMediaExpirationAction $expirationAction): JsonResponse {

        $avatar = $this->getUser()->getAvatar();

        $collection = 'avatar-pending';
        $media =  $mediaService->getMediaForObject( $this->getUser(), $collection );
        if ($media->isEmpty()) {
            $collection = 'avatar';
            $media = $mediaService->getMediaForObject($this->getUser(), $collection);
        }
        if ($media->isEmpty()) $collection = null;

        if ($avatar || !$media->isEmpty()) {
            if ($avatar) {
                $em->remove($this->getUser()->getAvatar());
                $this->getUser()->setAvatar(null);
            }

            if ($collection !== null)
                $mediaService->clearMediaFromObject( $this->getUser(), $collection);

            $clearCache("user_avatar_{$this->getUser()->getId()}");
            $em->flush();

            ($expirationAction)($this->getUser(), true);
        }

        return new JsonResponse();
    }

    /**
     * @param Media $media
     * @param EntityManagerInterface $em
     * @param InvalidateTagsInAllPoolsAction $clearCache
     * @return JsonResponse
     */
    #[Route(path: '/media/{id}', name: 'deleteSpecific', methods: ['DELETE'])]
    public function deleteSpecificMedia(
        #[MapEntity(id: 'id')]
        Media $media,
        EntityManagerInterface $em, InvalidateTagsInAllPoolsAction $clearCache, RecalculateMediaExpirationAction $expirationAction): JsonResponse {

        if ($media->getModelType() !== User::class || (int)$media->getModelId() !== $this->getUser()->getId())
            return new JsonResponse([], Response::HTTP_NOT_FOUND);

        if (!in_array( $media->getCollection(), ['avatar', 'avatar-pending', 'avatar-history'] ))
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        $em->remove($media);
        $em->flush();

        $clearCache("user_avatar_{$this->getUser()->getId()}");

        ($expirationAction)($this->getUser(), true);

        return new JsonResponse();
    }


    private function validateCrop(?array $crop, ?ImageInterface $image = null): ?array {
        if (!$crop) return null;

        try {
            list('height' => $h, 'width' => $w, 'x' => $x, 'y' => $y) = $crop;
            if ($h < 0 || $w < 0 || $x < 0 || $y < 0) return null;

            if ($image) {
                if (($x + $w > $image->width()) || ($y + $h > $image->height())) return null;
                if ($x === 0 && $y === 0 && $w === $image->width() && $h === $image->height()) return null;
            }

            return $crop;
        } catch (\Throwable $t) {
            return null;
        }

    }

    private function handleCreatedMedia(
        User $user,
        Media $media,
        ?string $format = null,
        ?array $crop_small = null,
        ?array $crop_default = null,
        ?array $crop_round = null,
    ): void {
        $all_conversions     = $media->findConversions();
        $classic_conversions = $media->findConversions(includeTags: ['classic']);
        $square_conversions  = $media->findConversions(includeTags: ['square']);
        $round_conversions   = $media->findConversions(includeTags: ['circular']);

        $classic_crop = $this->validateCrop( $crop_small, $media->transientImage );
        $square_crop = $this->validateCrop( $crop_default, $media->transientImage );
        $round_crop = $this->validateCrop( $crop_round, $media->transientImage );

        $media->tagConversion( $square_conversions, 'default' );

        if ($classic_crop) $media->modifyConversion( $classic_conversions, fn(AnonymousMediaVariant $variant) => $variant->prepend()->crop(
            $classic_crop['width'], $classic_crop['height'], $classic_crop['x'], $classic_crop['y'],
        ) );

        if ($square_crop) $media->modifyConversion( $square_conversions, fn(AnonymousMediaVariant $variant) => $variant->prepend()->crop(
            $square_crop['width'], $square_crop['height'], $square_crop['x'], $square_crop['y'],
        ) );

        if ($round_crop) $media->modifyConversion( $round_conversions, fn(AnonymousMediaVariant $variant) => $variant->prepend()->crop(
            $round_crop['width'], $round_crop['height'], $round_crop['x'], $round_crop['y'],
        ) );

        if ($media->transientImage->count() === 1) $media->modifyConversion( $all_conversions, function(AnonymousMediaVariant $variant) use ($format) {
            switch ($format) {
                case 'webp':
                    $variant->toWebp(quality: 90);
                    break;
                case 'lossless':
                    $variant->toWebp(quality: 100);
                    break;
            }
        } );

        $media->relatedCaches = ["user_avatar_{$user->getId()}"];
    }

    /**
     * @param JSONRequestParser $parser
     * @param PermissionHandler $permissionHandler
     * @param ConfMaster $conf
     * @param EntityManagerInterface $em
     * @param MediaService $mediaService
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/media', name: 'upload', methods: ['PUT'])]
    public function uploadMedia(
        JSONRequestParser $parser,
        PermissionHandler $permissionHandler,
        ConfMaster $conf,
        EntityManagerInterface $em,
        MediaService $mediaService,
    ): JsonResponse {
        $payload = $parser->get_base64('data');
        $pending = $parser->get('pending', true);
        $format = $parser->get('format', 'avif');
        $lossless = $format === 'lossless';
        if ($lossless) $format = 'webp';

        $user = $this->getUser();
        if ($permissionHandler->checkRestriction($user, AccountRestriction::RestrictionProfileAvatar))
            return new JsonResponse(status: Response::HTTP_FORBIDDEN);

        if (!$payload) return new JsonResponse(status: Response::HTTP_BAD_REQUEST);

        if (strlen( $payload ) > $conf->getGlobalConf()->get(MyHordesSetting::AvatarMaxSizeUpload))
            return new JsonResponse(['error' => UserHandler::ErrorAvatarTooLarge]);

        $media = $mediaService->addMediaToObjectFromBinaryString( $user, $payload, null, $pending ? 'avatar-pending' : 'avatar', Uuid::v7() );

        $this->handleCreatedMedia( $user, $media, $format, $parser->get_array( 'crop.small'), $parser->get_array( 'crop.default'), $parser->get_array( 'crop.round' ) );
        $em->persist( $media->setDeleteAt( Carbon::now()->addDay()->toDateTimeImmutable() ) );
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param Media $originalMedia
     * @param JSONRequestParser $parser
     * @param PermissionHandler $permissionHandler
     * @param EntityManagerInterface $em
     * @param MediaService $mediaService
     * @param KernelInterface $kernel
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/media/{id}', name: 'patch', methods: ['PATCH'])]
    public function patchMedia(
        #[MapEntity(id: 'id')]
        Media $originalMedia,
        JSONRequestParser $parser,
        PermissionHandler $permissionHandler,
        EntityManagerInterface $em,
        MediaService $mediaService,
        KernelInterface $kernel
    ): JsonResponse {

        $user = $this->getUser();
        $format = $parser->get('format', 'avif');

        if ($permissionHandler->checkRestriction($user, AccountRestriction::RestrictionProfileAvatar))
            return new JsonResponse(status: Response::HTTP_FORBIDDEN);

        if ($originalMedia->getModelType() !== User::class || (int)$originalMedia->getModelId() !== $this->getUser()->getId())
            return new JsonResponse([], Response::HTTP_NOT_FOUND);

        if ($originalMedia->getCollection() !== 'avatar')
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        $crops = ['small', 'default', 'round'];
        $crop_props = ['x', 'y', 'width', 'height'];
        $existing_crop = [
            'small' => $originalMedia->getLargestConversionByTag('classic')?->crop ?? [],
            'default' => $originalMedia->getLargestConversionByTag('square')?->crop ?? [],
            'round' => $originalMedia->getLargestConversionByTag('circular')?->crop ?? [],
        ];

        $same = true;
        foreach ($crops as $crop)
            foreach ($crop_props as $prop)
                $same = $same && $parser->get_int( "crop.$crop.$prop", -1 ) === Arr::get($existing_crop, "$crop.$prop", -1);

        if ($same) return new JsonResponse(['success' => true]);

        $media = $mediaService->addMediaToObjectFromFile( $user, "{$kernel->getProjectDir()}/public/storage/{$originalMedia->getUrl()}", 'avatar-pending', $originalMedia->getFilename() );

        $this->handleCreatedMedia( $user, $media, $format, $parser->get_array( 'crop.small'), $parser->get_array( 'crop.default'), $parser->get_array( 'crop.round' ) );

        $em->persist( $media->setDeleteAt( Carbon::now()->addDay()->toDateTimeImmutable() )->setMetaKey('source', $originalMedia->getMetaKey('source') ?? $originalMedia->getId()) );
        $em->persist( $originalMedia->pushMetaKey('copies', $media->getId(), true) );
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param Media $media
     * @param PermissionHandler $permissionHandler
     * @param EntityManagerInterface $em
     * @param MediaService $mediaService
     * @param RecalculateMediaExpirationAction $expirationAction
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/media/{id}', name: 'promote', methods: ['POST'])]
    public function promoteMedia(
        #[MapEntity(id: 'id')]
        Media $media,
        PermissionHandler $permissionHandler,
        EntityManagerInterface $em,
        MediaService $mediaService,
        RecalculateMediaExpirationAction $expirationAction
    ): JsonResponse {
        if ($media->getModelType() !== User::class || (int)$media->getModelId() !== $this->getUser()->getId())
            return new JsonResponse([], Response::HTTP_NOT_FOUND);

        if (!in_array( $media->getCollection(), ['avatar-pending', 'avatar-history'] ))
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        $user = $this->getUser();
        if ($permissionHandler->checkRestriction($user, AccountRestriction::RestrictionProfileAvatar))
            return new JsonResponse(status: Response::HTTP_FORBIDDEN);


        $existing = $mediaService->getMediaForObject( $user, 'avatar' );
        if (!$existing->isEmpty()) {
            foreach ($existing as $e) $mediaService->moveMediaToNewCollection( $e, 'avatar-history' );
            $em->flush();
        }

        if (!$mediaService->moveMediaToNewCollection( $media->setDeleteAt(null), 'avatar' ))
            return new JsonResponse([], Response::HTTP_CONFLICT);

        $em->flush();

        $expirationAction($this->getUser());
        return new JsonResponse(['success' => true]);
    }


    #[Route(path: '/media/{id}/props', name: 'mediaWorkCopy', methods: ['GET'])]
    public function getMediaWorkCopy(
        #[MapEntity(id: 'id')]
        Media $media
    ): JsonResponse {

        if ($media->getModelType() !== User::class || (int)$media->getModelId() !== $this->getUser()->getId())
            return new JsonResponse([], Response::HTTP_NOT_FOUND);

        if ($media->getCollection() !== 'avatar')
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        return new JsonResponse([
            'source'    => "/storage/{$media->getUrl()}",
            'default'   => $media->getLargestConversionByTag( 'square' )?->crop,
            'round'     => $media->getLargestConversionByTag( 'circular' )?->crop,
            'small'     => $media->getLargestConversionByTag( 'classic' )?->crop,
        ]);
    }
}
