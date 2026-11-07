<?php

namespace App\Controller\REST\Admin;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Entity\Award;
use App\Entity\Emotes;
use App\Entity\ForumPoll;
use App\Entity\ForumPollAnswer;
use App\Entity\ForumUsagePermissions;
use App\Entity\GlobalPoll;
use App\Entity\User;
use App\Response\AjaxResponse;
use App\Service\Actions\User\GetUnlockedEmotesAction;
use App\Service\ConfMaster;
use App\Service\ErrorHelper;
use App\Service\HTMLService;
use App\Service\JSONRequestParser;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateMalformedStringException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[Route(path: '/rest/v1/admin/polls', name: 'rest_admin_polls_', condition: "request.headers.get('Accept') === 'application/json'")]
#[IsGranted('ROLE_ELEVATED')]
#[GateKeeperProfile('skip')]
class PollController extends CustomAbstractCoreController
{
    /**
     * @param Packages $asset
     * @param GetUnlockedEmotesAction $getEmotes
     * @return JsonResponse
     * @throws DateMalformedStringException
     */
    #[Route(path: '', name: 'index', methods: ['GET'])]
    #[Route(path: '/index_data', name: 'index_data', methods: ['GET'])]
    public function index(Packages $asset, GetUnlockedEmotesAction $getEmotes): JsonResponse
    {
        $user = $this->getUser();

        // Emotes
        $emotes = $getEmotes($user);

        // Flags
        $flags = [];
        foreach (array_merge(['multi'], $this->generatedLangsCodes) as $lang) {
            $flags[$lang] = $asset->getUrl("build/images/lang/$lang.png");
        }

        // Icons
        $icons = [
            'arrowUp' => $asset->getUrl('build/images/icons/arrowUp.png'),
            'arrowDown' => $asset->getUrl('build/images/icons/arrowDown.png'),
            'delete' => $asset->getUrl('build/images/icons/small_trash_red.png'),
        ];

        // Default dates
        $now = CarbonImmutable::now();
        $startDate = $now->format('Y-m-d');
        $endDate = $now->addDays(7)->format('Y-m-d');
        $minEndDate = $now->addDay()->format('Y-m-d');

        // Translation strings
        $strings = [
            'create_new_poll' => $this->translator->trans('Eine neue Umfrage anlegen', [], 'admin'),
            'editor_settings' => $this->translator->trans('Editoreinstellungen', [], 'admin'),
            'current_edit_lang' => $this->translator->trans('Aktuelle Bearbeitungssprache', [], 'admin'),
            'help_edit_lang' => $this->translator->trans('Diese Einstellung steuert, welche Eingabefelder sichtbar sind. Unabhängig davon werden Umfragen immer in allen Sprachen erstellt, es müssen also alle Felder in allen Sprachen ausgefüllt werden.', [], 'admin'),
            'help' => $this->translator->trans('Hilfe', [], 'global'),
            'poll_data' => $this->translator->trans('Umfragedaten', [], 'admin'),
            'poll_title' => $this->translator->trans('Titel der Umfrage', [], 'admin'),
            'poll_start' => $this->translator->trans('Start der Umfrage', [], 'admin'),
            'poll_end' => $this->translator->trans('Ende der Umfrage', [], 'admin'),
            'poll_premature' => $this->translator->trans('Vorläufige Ergebnisse anzeigen', [], 'admin'),
            'poll_premature_start' => $this->translator->trans('Verläufige Ergebnisse anzeigen ab', [], 'admin'),
            'poll_multi' => $this->translator->trans('Mehrere Antworten möglich (Multiple Choice)', [], 'admin'),
            'short_description' => $this->translator->trans('Kurzbeschreibung', [], 'admin'),
            'poll_description' => $this->translator->trans('Beschreibung der Umfrage', [], 'admin'),
            'answers' => $this->translator->trans('Antworten', [], 'admin'),
            'answer_title' => $this->translator->trans('Antwortstext', [], 'admin'),
            'answer_desc' => $this->translator->trans('Beschreibung', [], 'admin'),
            'add_answer' => $this->translator->trans('Antwort hinzufügen', [], 'admin'),
            'finish_editing' => $this->translator->trans('Bearbeitung abschließen', [], 'admin'),
            'submit_poll' => $this->translator->trans('Umfrage erstellen', [], 'admin'),
            'confirm' => $this->translator->trans('Bestätigen?', [], 'global'),
            'missing_fields' => $this->translator->trans('Für das Feld "{field}" fehlen Eingaben in folgenden Sprachen: {langs}.', [], 'admin'),
            'min_answers_error' => $this->translator->trans('Es müssen mindestens zwei Antworten hinzugefügt werden.', [], 'admin'),
            'success_notice' => 'OK',
        ];

        return new JsonResponse([
            'langsCodes' => $this->generatedLangsCodes,
            'userLang' => $user->getLanguage() ?? 'en',
            'emotes' => $emotes,
            'flags' => $flags,
            'icons' => $icons,
            'defaults' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'minEndDate' => $minEndDate,
            ],
            'strings' => $strings,
        ]);
    }

    /**
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param HTMLService $html
     * @return Response
     */
    #[Route(path: '', name: 'create', methods: ['POST'])]
    public function create(
        JSONRequestParser $parser,
        EntityManagerInterface $em,
        HTMLService $html,
    ): Response
    {
        $p = match(true) {
            $this->isGranted('ROLE_SUB_ADMIN') => ForumUsagePermissions::PermissionOwn,
            $this->isGranted('ROLE_CROW') => ForumUsagePermissions::PermissionReadWrite | ForumUsagePermissions::PermissionFormattingModerator,
            default => ForumUsagePermissions::PermissionReadWrite | ForumUsagePermissions::PermissionFormattingOracle,
        };

        $format_html = function (&$data) use ($p, $html): bool {
            foreach ($this->generatedLangsCodes as $lang) {
                $str = trim($data[$lang] ?? '');
                if (mb_strlen($str) < 3)
                    return false;

                if (!$html->htmlPrepare($this->getUser(), $p, false, $data[$lang], null, $insight))
                    return false;

                if ($insight->text_length < 3)
                    return false;
            }
            return true;
        };

        $title = $parser->get_array('title');
        $desc = $parser->get_array('desc');
        $premature = (bool)$parser->get('premature');
        $multiple = (bool)$parser->get('multiple');
        $preview = $parser->get_array('preview');

        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $parser->get('start', '-1'));
            $end = CarbonImmutable::createFromFormat('Y-m-d', $parser->get('end', '-1'));
            $reveal_at = CarbonImmutable::createFromFormat('Y-m-d', $parser->get('date_premature', '-1'));
        } catch (Throwable $e) {
            return new JsonResponse(['e' => $e->getMessage()], Response::HTTP_NOT_ACCEPTABLE);
        }

        if (!$premature) {
            $reveal_at = null;
        }

        if ($end->isPast() || $start >= $end)
            return new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE);

        $answers = array_values($parser->get_array('answers'));
        if (count($answers) < 2)
            return new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE);

        if (!$format_html($title) || !$format_html($desc) || !$format_html($preview))
            return new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE);

        $answer_data = [];
        foreach ($answers as &$answer) {
            if (!$format_html($answer['title']) || !$format_html($answer['desc']))
                return new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE);

            $answer_data[] = [$answer, new ForumPollAnswer()->setNum(0)];
        }

        $poll = new ForumPoll()->setOwner($this->getUser())->setClosed(false);
        foreach ($answer_data as [, $answer_entity]) {
            $poll->addAnswer($answer_entity);
        }


        $em->persist($poll);
        $em->flush();

        $global_poll = new GlobalPoll()
            ->setPoll($poll)
            ->setStartDate($start->toMutable())
            ->setEndDate($end->toMutable())
            ->setShowResultsImmediately($premature)
            ->setMultipleChoice($multiple)
            ->setRevealDate($reveal_at?->toMutable());

        foreach ($this->allLangsCodes as $lang) {
            if ($lang !== 'ach') {
                $global_poll
                    ->setTitleByLang($lang, $title[$lang])
                    ->setDescriptionByLang($lang, $desc[$lang])
                    ->setShortDescriptionByLang($lang, $preview[$lang]);
                foreach ($answer_data as [['title' => $answer_title, 'desc' => $answer_desc], $entity]) {
                    $global_poll
                        ->setAnswerTitleByLang($entity, $lang, $answer_title[$lang])
                        ->setAnswerDescriptionByLang($entity, $lang, $answer_desc[$lang]);
                }
            }
        }

        $em->persist($global_poll);
        $em->flush();

        return new JsonResponse([
            'url' => $this->generateUrl('soul_polls', ['id' => $global_poll->getId()])]
        );
    }

    /**
     * @param GlobalPoll $poll
     * @param string $action
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route(path: '/{id}', name: 'delete', defaults: ['action' => 'delete'], methods: ['DELETE'])]
    #[Route(path: '/{id}/start', name: 'start', defaults: ['action' => 'start'], methods: ['PATCH'])]
    #[Route(path: '/{id}/close', name: 'close', defaults: ['action' => 'close'], methods: ['PATCH'])]
    public function modify(
        #[MapEntity(id: 'id')]
        GlobalPoll $poll,
        string $action,
        EntityManagerInterface $em
    ): Response {

        if (!$this->isGranted('ROLE_SUB_ADMIN'))
            return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $now = Carbon::now();
        if ($action === 'start' && $poll->getStartDate() > $now)
            $em->persist( $poll->setStartDate($now) );
        elseif ($action === 'close' && $poll->getStartDate() < $now && $poll->getEndDate() > $now)
            $em->persist( $poll->setEndDate($now) );
        elseif ($action === 'delete') {
            $em->remove( $poll );
        }

        $em->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }
}
