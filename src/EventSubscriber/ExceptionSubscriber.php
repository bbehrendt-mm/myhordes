<?php


namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\Configuration\ExternalTokenPurpose;
use App\Enum\Configuration\ExternalTokenType;
use App\Enum\Configuration\MyHordesSetting;
use App\Messages\Discord\DiscordMessage;
use App\Service\Actions\External\GetExternalTokenWithFallbackAction;
use App\Service\ConfMaster;
use DiscordWebhooks\Client;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Throwable;

class ExceptionSubscriber implements EventSubscriberInterface
{
    private string $report_path;
    private string $version;
    private ?array $gitlabIssueMail;

    public function __construct(
        ConfMaster                              $conf,
        ParameterBagInterface                   $params,
        private readonly MailerInterface        $mail,
        private readonly MessageBusInterface    $bus,
        private readonly ManagerRegistry        $mr,
		private readonly TokenStorageInterface	$ts,
        private readonly Packages               $asset,
        private readonly UrlGeneratorInterface  $urlGenerator,
        private readonly GetExternalTokenWithFallbackAction $get_endpoints,
    ) {
        $this->report_path = "{$params->get('kernel.project_dir')}/var/reports";

        $version_file = "{$params->get('kernel.project_dir')}/VERSION";
        $this->version = file_exists( $version_file ) ? file_get_contents( $version_file ) : 'NOVER';

        $this->gitlabIssueMail['to']   = $conf->getGlobalConf()->get( MyHordesSetting::HookFatalMailTo );
        $this->gitlabIssueMail['from'] = $conf->getGlobalConf()->get( MyHordesSetting::HookFatalMailFrom );
    }

    public function onKernelException(ExceptionEvent $event): void
    {

        if (is_a( $event->getThrowable(), HttpException::class )) return;

        $error_id = md5( $event->getThrowable()->getFile() . "@" . $event->getThrowable()->getLine() . '@' . $this->version );
        $report_path = "{$this->report_path}/{$error_id}/";

        $discord_file = "{$report_path}/discord";
        $mail_file = "{$report_path}/mail";

		/** @var User $user */
		$user = $this->ts->getToken()?->getUser();

        if (!file_exists($report_path)) mkdir( $report_path, 0777, true );

        if (!file_exists($discord_file)) {
            $this->mr->resetManager();
            file_put_contents( $discord_file, "".time() );

            try {
                $endpoints = ($this->get_endpoints)(
                    ExternalTokenType::DiscordWebhook,
                    ExternalTokenPurpose::ErrorReporting,
                    MyHordesSetting::HookFatalDiscord
                );
            } catch (Throwable $e) {
                $endpoints = new ArrayCollection();
            }

            try {
                $dc_avatar =
                    $this->urlGenerator->generate( 'home', [],  UrlGeneratorInterface::ABSOLUTE_URL ) .
                    $this->asset->getUrl('build/images/default/user-nuntius.png');
            } catch (Throwable $e) {
                $dc_avatar = null;
            }

            foreach ($endpoints as $endpoint)
                $this->bus->dispatch( new DiscordMessage(
                    new Client( $endpoint )
                        ->username( 'Corvus Nuntius' )
                        ->avatar( $dc_avatar )
                        ->message(":sos: **Reporting an exception in MyHordes**\n" .
                                  "```fix\n[{$event->getThrowable()->getMessage()}]\n```\n" .
			    				  ($user !== null ? "User that thrown the exception: {$user->getUsername()}\n" : "") .
			    				  "URL of the error: {$event->getRequest()->getPathInfo()}\n" .
                                  "*{$event->getThrowable()->getFile()}*\nLine *{$event->getThrowable()->getLine()}*\n\n"
                        )
                ) );
        }

        if ($this->gitlabIssueMail['from'] && $this->gitlabIssueMail['to'] && !file_exists($mail_file)) {
            try {
                $this->mail->send( new Email()
                    ->from( $this->gitlabIssueMail['from'] )
                    ->to( $this->gitlabIssueMail['to'] )
                    ->subject( "Automatic Error Report {$error_id}" )
                    ->text(
                        "**Reporting an exception in MyHordes**\n" .
                        "```\n[{$event->getThrowable()->getMessage()}]\n```\n" .
                        "*{$event->getThrowable()->getFile()}*\nLine *{$event->getThrowable()->getLine()}*\n\n" .
				    	   ($user !== null ? "User that thrown the exception: {$user->getUsername()}\n\n" : "") .
				    	   "URL of the error: {$event->getRequest()->getPathInfo()}\n\n" .
                        "See attached stack trace for more information.\n" .
                        "/confidential\n/label ~Bug ~High ~Automatic"
                    )
                    ->attach( $event->getThrowable()->getTraceAsString(), 'stack.txt', 'text/plain' ) );
                file_put_contents( $mail_file, "".time() );
            } catch (Throwable $e) {}
        }

    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
