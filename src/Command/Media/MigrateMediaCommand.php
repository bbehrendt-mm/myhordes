<?php


namespace App\Command\Media;


use App\Entity\Award;
use App\Entity\ExternalApp;
use App\Entity\OfficialGroup;
use App\Entity\User;
use App\Service\CommandHelper;
use App\Service\Media\MediaService;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:media:migrate',
    description: 'Migrate media files.'
)]
class MigrateMediaCommand extends Command
{
    private readonly string $basePath;

    public function __construct(
        KernelInterface $kernel,
        private readonly CommandHelper $helper,
        private readonly MediaService $mediaService,
    )
    {
        $this->basePath = "{$kernel->getProjectDir()}/assets/img/default/";
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Assign default media files.')

            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing media')
            ->addOption('keep', null, InputOption::VALUE_NONE, 'Keep legacy data')

            ->addOption('groups', null, InputOption::VALUE_NONE, 'Perform migration for official groups')
            ->addOption('apps', null, InputOption::VALUE_NONE, 'Perform migration for external apps')
            ->addOption('awards', null, InputOption::VALUE_NONE, 'Perform migration for custom awards')
            ->addOption('users', null, InputOption::VALUE_NONE, 'Perform migration for user avatars')
            ->addOption('users-skip-gif', null, InputOption::VALUE_NONE, 'Do not process gif avatars')
        ;
        parent::configure();
    }

    protected function handleGroups(bool $force, bool $keep, OutputInterface $output): void {
        $this->helper->leChunk( $output, OfficialGroup::class, 1, [], true, false, function(OfficialGroup $g) use ($force,$keep) {
            if (!$force && $this->mediaService->hasMediaForObject( $g, 'icon' )) return false;

            if ($g->getAvatarName()) {
                $this->mediaService->addMediaToObjectFromResource( $g, $g->getIcon(), MediaService::extensionToMimeType( $g->getAvatarExt() ), 'icon' );
                if (!$keep) $g
                    ->setAvatarName(null)
                    ->setAvatarExt(null)
                    ->setIcon(null);

                return true;
            }

            $this->mediaService->addMediaToObjectFromFile( $g, $this->basePath . $g->getSemantic()->defaultImageFileName(), 'icon' );

            return true;
        } );

    }

    protected function handleExternalApps(bool $force, bool $keep, OutputInterface $output): void {
        $this->helper->leChunk( $output, ExternalApp::class, 1, [], true, false, function(ExternalApp $e) use ($force,$keep) {
            if (!$force && $this->mediaService->hasMediaForObject( $e, 'icon' )) return false;

            if ($e->getImageName()) {
                $this->mediaService->addMediaToObjectFromResource( $e, $e->getImage(), MediaService::extensionToMimeType( $e->getImageFormat() ), 'icon' );
                if (!$keep) $e
                    ->setImage(null)
                    ->setImageFormat(null)
                    ->setImageName(null);

                return true;
            }

            return false;
        } );

    }

    protected function handleAwards(bool $force, bool $keep, OutputInterface $output): void {
        $this->helper->leChunk( $output, Award::class, 1,
            Criteria::create()->where( Criteria::expr()->isNotNull( 'customIconName' ) ), true, false, function(Award $a) use ($force,$keep) {

            if (!$force && $this->mediaService->hasMediaForObject( $a, 'icon' )) return false;

            if ($a->getCustomIconName()) {
                $this->mediaService->addMediaToObjectFromResource( $a, $a->getCustomIcon(), MediaService::extensionToMimeType( $a->getCustomIconFormat() ), 'icon' );
                if (!$keep) $a
                    ->setCustomIcon(null)
                    ->setCustomIconFormat(null)
                    ->setCustomIconName(null);

                return true;
            }

            return false;
        } );
    }

    protected function handleUsers(bool $force, bool $keep, OutputInterface $output, bool $skip_gif): void {

        $existing = $force ? [] : $this->mediaService->getObjectIDsWithMedia(User::class, 'avatar');
        if (empty($existing)) $existing = [-1];

        $this->helper->leChunk( $output, User::class, 1,
                                Criteria::create()
                                    ->where( Criteria::expr()->isNotNull( 'avatar' ) )
                                    ->andWhere( Criteria::expr()->notIn('id', $existing) ), true, false, function(User $u, EntityManagerInterface $em, $debug) use ($output, $force,$keep,$skip_gif) {

                if (!$force && $this->mediaService->hasMediaForObject( $u, 'avatar' )) return false;

                $avatar = $u->getAvatar();
                if ($skip_gif && $avatar->getFormat() === 'gif') return false;

                $small = $avatar->getSmallImage();

                $media = $this->mediaService->addMediaToObjectFromResource( $u, $avatar->getImage(), MediaService::extensionToMimeType( $avatar->getFormat() ), 'avatar' );
                $media->unregisterConversion( $media->findConversions(includeTags: ['classic', 'square']) );


                $animated = $media->transientImage->isAnimated() ? ['animated'] : [];
                $debugStr = "{$media->transientImage->width()}x{$media->transientImage->height()} ({$avatar->getFormat()}, {$media->transientImage->count()} frame/s)";

                $debug( $debugStr );

                if (($media->transientImage->width() / $media->transientImage->height()) > 2.5) {
                    $debug( "$debugStr | Handling as classic avatar" );

                    $tags = ['default', 'classic', ...$animated];
                    $media->registerConversion('legacy-classic', $tags);

                    if ($media->transientImage->width() > 90) {
                        $media->registerConversion('legacy-classic-hd', $tags);
                        $this->mediaService->storePreConvertedImageAsConversion( $media, $media->transientImage, $media->transientImage->encode(), 'legacy-classic-hd' );

                        $down = (clone $media->transientImage)->scaleDown(90,30);
                        $this->mediaService->storePreConvertedImageAsConversion( $media, $down, $down->encode(), 'legacy-classic' );
                    } else $this->mediaService->storePreConvertedImageAsConversion( $media, $media->transientImage, $media->transientImage->encode(), 'legacy-classic' );
                } else {
                    $debug( "$debugStr | Handling as modern avatar" );

                    $tags = ['default', 'square', ...$animated];
                    $media->registerConversion('legacy-default', $tags);

                    if ($media->transientImage->width() > 100) {
                        $media->registerConversion('legacy-default-hd', $tags);
                        $this->mediaService->storePreConvertedImageAsConversion( $media, $media->transientImage, $media->transientImage->encode(), 'legacy-default-hd' );

                        $down = (clone $media->transientImage)->scaleDown(100,100);
                        $this->mediaService->storePreConvertedImageAsConversion( $media, $down, $down->encode(), 'legacy-default' );
                    } else $this->mediaService->storePreConvertedImageAsConversion( $media, $media->transientImage, $media->transientImage->encode(), 'legacy-default' );

                    if ($small !== null) {
                        $debug( "$debugStr | Handling additional classic avatar" );

                        $image_small = new ImageManager( Driver::class, strip: true )->read( stream_get_contents( $small ) );
                        $tags = ['classic', ...($image_small->isAnimated() ? ['animated'] : [])];
                        $media->registerConversion('legacy-classic', $tags);

                        if ($image_small->width() > 90) {
                            $media->registerConversion('legacy-classic-hd', $tags);
                            $this->mediaService->storePreConvertedImageAsConversion( $media, $image_small, $image_small->encode(), 'legacy-classic-hd' );

                            $down = (clone $image_small)->scaleDown(90,30);
                            $this->mediaService->storePreConvertedImageAsConversion( $media, $down, $down->encode(), 'legacy-classic' );
                        } else $this->mediaService->storePreConvertedImageAsConversion( $media, $image_small, $image_small->encode(), 'legacy-classic' );
                    }
                }

                $em->persist( $media );

                if (!$keep) {
                    $u->setAvatar(null);
                    $em->remove( $avatar );
                }

                return false;
            }, clearEM: true );

    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $keep = $input->getOption('keep');

        if ($input->getOption('groups')) $this->handleGroups($force, $keep, $output);
        if ($input->getOption('apps')) $this->handleExternalApps($force, $keep, $output);
        if ($input->getOption('awards')) $this->handleAwards($force, $keep, $output);
        if ($input->getOption('users')) $this->handleUsers($force, $keep, $output, $input->getOption('users-skip-gif'));

        return 0;
    }
}
