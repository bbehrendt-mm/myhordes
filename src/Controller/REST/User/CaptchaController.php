<?php

namespace App\Controller\REST\User;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Response\AjaxResponse;
use App\Security\Captcha\Question\CitizenQuestion;
use App\Security\Captcha\Question\TriviaQuestion;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use App\Service\RateLimitingFactoryProvider;
use App\Service\QuestionFactoryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/rest/v1/user/captcha', name: 'rest_user_captcha_')]
#[GateKeeperProfile('skip')]
class CaptchaController extends CustomAbstractCoreController
{
	const SESSION_CAPTCHA_USER =			'captcha_user';
	const SESSION_CAPTCHA_CITIZEN =			'captcha_citizen';
	const SESSION_CAPTCHA_TOKEN =			'captcha_token';
	const SESSION_CAPTCHA_RATE_LIMITERS =	'captcha_rate_limiters';

    #[Route(path: '/hordes', name: 'hordes', methods: ['GET'])]
    public function index(
		SessionInterface $session,
		RateLimitingFactoryProvider $rateLimiter,
		QuestionFactoryService $questionFactory
	): JsonResponse {
		$user = $session->get(CaptchaController::SESSION_CAPTCHA_USER);

		if(!$user) {
			return AjaxResponse::error( ErrorHelper::ErrorPermissionError );
		}

		// if(!$rateLimiter->captchaAttempts->create($user->getId())->consume(1)->isAccepted()) {
        //     return AjaxResponse::error( ErrorHelper::ErrorCaptchaRateLimit);
		// }

		return new JsonResponse([
			'strings' => [
				'common' => [
					'title' => 'Pas si vite',
                    'prompt' => 'Avant de continuer, êtes-vous certain de ne rien avoir oublié ?',
					'submit' => 'Confirmer',
					'abort' => 'Annuler',
				],
				'quizz' => $this->getRandomQuestion(
					$session,
					$questionFactory
				),
			],
			'submit' => $this->generateUrl('rest_user_captcha_hordes_submit'),
            'captcha' => true,
			'success' => true,
        ]);
    }

    #[Route(path: '/hordes', name: 'hordes_submit', methods: ['POST'])]
    public function hordesCaptcha(
		JSONRequestParser $parser,
		SessionInterface $session,
		RateLimitingFactoryProvider $rateLimiter,
	): JsonResponse {
		$user = $session->get(CaptchaController::SESSION_CAPTCHA_USER);

		if(!$user) {
			$this->clearCaptcha($session);
			return AjaxResponse::error( ErrorHelper::ErrorPermissionError );
		}

		$captchaRateLimiter = $rateLimiter->captchaAttempts->create($user->getId());

		if(!$captchaRateLimiter->consume(0)->isAccepted()) {
			$this->clearCaptcha($session);
            return AjaxResponse::error( ErrorHelper::ErrorCaptchaRateLimit);
		}

		$success = $parser && $parser->get('id') && $parser->get('id') === $session->get(CaptchaController::SESSION_CAPTCHA_TOKEN);
		if(!$success) {
			// $captchaRateLimiter->consume(1);
			$this->clearCaptcha($session);
			return AjaxResponse::error( ErrorHelper::ErrorCaptchaFail );
		}

		$rate_limiters = $session->get(CaptchaController::SESSION_CAPTCHA_RATE_LIMITERS);

		foreach($rate_limiters as $rate_limiter) {
			$rateLimiter->{$rate_limiter["name"]}->create($rate_limiter["id"])->reset();
		}

		$this->clearCaptcha($session);

		return new JsonResponse([
            'success' => true,
        ]);
    }

	protected function clearCaptcha(SessionInterface $session) {
		$session->remove(CaptchaController::SESSION_CAPTCHA_USER);
		$session->remove(CaptchaController::SESSION_CAPTCHA_CITIZEN);
		$session->remove(CaptchaController::SESSION_CAPTCHA_TOKEN);
		$session->remove(CaptchaController::SESSION_CAPTCHA_RATE_LIMITERS);
	}

	protected function getRandomQuestion(
		SessionInterface $session,
		QuestionFactoryService $questionFactory
	) {
		$questions = $this->getAllQuestions(
			$session,
			$questionFactory
		);
		$question = $questions[array_rand($questions)];

		$quizz = [
			'prompt' => $question->getPrompt(),
			'promptIcon' => $question->getPromptIcon(),
			'promptContext' => $question->getPromptContext(),
			'options' => array_map(fn($answer) => ['value'=> $answer], $question->getOptions()),
		];

		foreach($quizz['options'] as $i => $option) {
			$id = md5(random_int(1000000,10000000). $option['value']); 
			$quizz['options'][$i]['id'] = $id;

			if($i === 0) {
				$session->set(CaptchaController::SESSION_CAPTCHA_TOKEN, $id);
				$quizz['options'][$i]['value'] .= ' debug: this';
			}
		}
		shuffle($quizz['options']);
		return $quizz;
	}

	protected function getAllQuestions(
		SessionInterface $session,
		QuestionFactoryService $questionFactory
	) {
		$questions = [];

		$questions = array_merge($questions, TriviaQuestion::getAll($questionFactory));

		$citizen = $session->get(CaptchaController::SESSION_CAPTCHA_CITIZEN, null);
		if($citizen) {
			$questions = array_merge($questions, CitizenQuestion::getAll($questionFactory, $citizen));
		}

		return $questions;
	}
}