<?php

namespace App\Controller\REST\User\Settings;

use App\Entity\AccountRestriction;
use App\Entity\Avatar;
use App\Entity\Media;
use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\Actions\Cache\InvalidateTagsInAllPoolsAction;
use App\Service\ConfMaster;
use App\Service\JSONRequestParser;
use App\Service\Media\ImageService;
use App\Service\Media\MediaService;
use App\Service\PermissionHandler;
use App\Service\UserHandler;
use App\Structures\Image;
use App\Structures\Media\AnonymousMediaVariant;
use App\Structures\Media\MediaVariantInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Intervention\Image\Interfaces\ImageInterface;
use Psr\Cache\InvalidArgumentException;
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

                    'edit_help' => $trans->trans('MyHordes verwendet drei unterschiedliche Bildformate für dein Profilbild. Standartmäßig werden diese automatisch aus deinem ausgewählten Bild ermittelt. Du kannst die Bildausschnitte aber auch selbst wählen, wenn du möchtest. Klicke dazu einfach bei dem entsprechenden Bildformat auf "Bearbeiten".', [], 'soul'),
                    'edit_help2' => $trans->trans('Bist du fertig, klicke auf "Profilbild speichern" um deinen Avatar hochzuladen. Dein Bild wird dann vom System automatisch komprimiert und zugeschnitten.', [], 'soul'),
                    'edit_help3' => $trans->trans('Das automatische Komprimieren und Zuschneiden kann einige Sekunden dauern. Sobald dein Bild fertig verarbeitet wurde, siehst du noch einmal eine Vorschau und kannst das Bild dann final als deinen neuen Avatar festlegen.', [], 'soul'),

                    'format_upload' => $trans->trans('Originales Bild', [], 'soul'),
                    'format_default' => $trans->trans('Normale Anzeige', [], 'soul'),
                    'format_round' => $trans->trans('Runde Anzeige', [], 'soul'),
                    'format_small' => $trans->trans('Komprimierte Anzeige', [], 'soul'),

                    'info' => $trans->trans('{x} × {y} Pixel, {size}', [], 'soul'),
                    'dimensions' => $trans->trans('Größe {x} × {y} Pixel', [], 'soul'),
                    'none' => $trans->trans('Nicht hochgeladen', [], 'soul'),
                    'fallback' => $trans->trans('Automatisch erzeugt', [], 'soul'),

                    'action_delete' => $trans->trans('Profilbild löschen', [], 'soul'),
                    'action_edit' => $trans->trans('Neues Profilbild hochladen', [], 'soul'),
                    'action_modify' => $trans->trans('Profilbild bearbeiten', [], 'soul'),
                    'action_create' => $trans->trans('Profilbild hochladen', [], 'soul'),
                    'action_cancel' => $trans->trans('Abbrechen', [], 'global'),
                    'action_upload' => $trans->trans('Profilbild speichern', [], 'soul'),

                    'confirm' => $trans->trans('Bestätigen?', [], 'global'),

                    'error_single_file' => $trans->trans('Bitte wähle nur eine einzige Datei aus.', [], 'soul'),
                    'error_too_large' => $trans->trans('Die Datei ist zu groß.', [], 'soul'),
                    'error_unknown_format' => $trans->trans('Dieses Dateiformat wird nicht unterstützt.', [], 'soul'),

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
            ]
        ]);
    }

    private function renderAvatar(?Media $media, string $tag): ?array {
        $conversion = $media?->getLargestConversionByTag( $tag );
        if (!$conversion) return null;

        return [
            'url' => $conversion->url,
            'format' => MediaService::mimeTypeToExtension( $conversion->mime, false ),
            'size' => $conversion->size,
        ];
    }

    /**
     * @param MediaService $mediaService
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/media', name: 'list', methods: ['GET'])]
    public function fetchMedia(MediaService $mediaService): JsonResponse {
        $media = $mediaService->getSingleMediaForObject( $this->getUser(), 'avatar' );
        return new JsonResponse( [
            'default'   => $this->renderAvatar($media, 'square'),
            'round'     => $this->renderAvatar($media, 'circular'),
            'small'     => $this->renderAvatar($media, 'classic'),
        ] );
    }

    /**
     * @param EntityManagerInterface $em
     * @param InvalidateTagsInAllPoolsAction $clearCache
     * @param MediaService $mediaService
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/media', name: 'delete', methods: ['DELETE'])]
    public function deleteMedia(EntityManagerInterface $em, InvalidateTagsInAllPoolsAction $clearCache, MediaService $mediaService): JsonResponse {

        $avatar = $this->getUser()->getAvatar();
        $media = $mediaService->getMediaForObject( $this->getUser(), 'avatar' );

        if ($avatar || !$media->isEmpty()) {
            if ($avatar) {
                $em->remove($this->getUser()->getAvatar());
                $this->getUser()->setAvatar(null);
            }

            $mediaService->clearMediaFromObject( $this->getUser(), 'avatar' );

            $clearCache("user_avatar_{$this->getUser()->getId()}");
            $em->flush();
        }

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

    /**
     * @param JSONRequestParser $parser
     * @param PermissionHandler $permissionHandler
     * @param ConfMaster $conf
     * @param EntityManagerInterface $em
     * @param InvalidateTagsInAllPoolsAction $clearCache
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
        $format = $parser->get('format', 'avif');
        $lossless = $format === 'lossless';
        if ($lossless) $format = 'webp';

        $user = $this->getUser();
        if ($permissionHandler->checkRestriction($user, AccountRestriction::RestrictionProfileAvatar))
            return new JsonResponse(status: Response::HTTP_FORBIDDEN);

        if (!$payload) return new JsonResponse(status: Response::HTTP_BAD_REQUEST);

        if (strlen( $payload ) > $conf->getGlobalConf()->get(MyHordesSetting::AvatarMaxSizeUpload))
            return new JsonResponse(['error' => UserHandler::ErrorAvatarTooLarge]);

        $media = $mediaService->addMediaToObjectFromBinaryString( $user, $payload, null, 'avatar', Uuid::v7() );

        $all_conversions     = $media->findConversions();
        $classic_conversions = $media->findConversions(includeTags: ['classic']);
        $square_conversions  = $media->findConversions(includeTags: ['square']);
        $round_conversions   = $media->findConversions(includeTags: ['circular']);

        $classic_crop = $this->validateCrop( $parser->get_array( 'crop.small'), $media->transientImage );
        $square_crop = $this->validateCrop( $parser->get_array( 'crop.default'), $media->transientImage );
        $round_crop = $this->validateCrop( $parser->get_array( 'crop.round' ), $media->transientImage );

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
        $em->persist( $media );
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
