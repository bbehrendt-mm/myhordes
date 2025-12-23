<?php


namespace App\Command\Media;


use App\Entity\Avatar;
use App\Entity\ExternalApp;
use App\Entity\OfficialGroup;
use App\Enum\OfficialGroupSemantic;
use App\Service\CommandHelper;
use App\Service\Media\ImageService;
use App\Service\Media\MediaService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
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

    protected function handleExternalApp(bool $force, bool $keep, OutputInterface $output): void {
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $keep = $input->getOption('keep');

        if ($input->getOption('groups')) $this->handleGroups($force, $keep, $output);
        if ($input->getOption('apps')) $this->handleExternalApp($force, $keep, $output);

        return 0;
    }
}
