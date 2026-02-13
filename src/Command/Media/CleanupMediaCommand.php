<?php


namespace App\Command\Media;


use App\Entity\Award;
use App\Entity\ExternalApp;
use App\Entity\Media;
use App\Entity\OfficialGroup;
use App\Entity\User;
use App\Service\CommandHelper;
use App\Service\Media\MediaService;
use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Zenstruck\ScheduleBundle\Attribute\AsScheduledTask;

#[AsCommand(
    name: 'app:media:clean',
    description: 'Clean up media files.'
)]
#[AsScheduledTask('55 * * * *', description: 'Auto-clean expired media entries.')]
class CleanupMediaCommand extends Command
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Remove expired media files.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not actually perform any changes and instead simply print what media entities would be removed.')
        ;
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dry_run = $input->getOption('dry-run');

        /** @var Collection<Media> $affected */
        $affected = $this->entityManager->getRepository(Media::class)->matching(Criteria::create()
            ->where(Criteria::expr()->isNotNull('deleteAt'))
            ->andWhere(Criteria::expr()->lte('deleteAt', Carbon::now()->toDateTimeImmutable()))
        );

        $count = $affected->count();


        if ($dry_run) {
            if ($count > 0) {
                $table = new Table($output);
                $table->setHeaders(['ID', 'Model Name', 'Model ID', 'Expiration Date']);

                foreach ($affected as $media)
                    $table->addRow([$media->getId(), $media->getModelType(), $media->getModelID(), $media->getDeleteAt()->format('Y-m-d H:i:s')]);

                $table->render();
            }

            $output->writeln("<info>{$count}</info> media entries are scheduled for removal.");

        } else {

            foreach ($affected as $media) {
                $this->entityManager->remove($media);
                $this->entityManager->flush();
            }

            $output->writeln("<info>{$count}</info> media entries removed.");
        }

        return 0;
    }
}
