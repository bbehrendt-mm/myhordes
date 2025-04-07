<?php

namespace App\Security\Captcha\Question;

use App\Entity\Citizen;
use App\Service\QuestionFactoryService;

/**
 * A Question involving a Citizen of a Town
 */
abstract class CitizenQuestion extends Question {

	public function __construct(
		protected Citizen $citizen,
		...$autowired
	) {
		parent::__construct(...$autowired);
	}

	static function getAll(
		QuestionFactoryService $factory,
		Citizen $citizen
	): array {
		return [
			$factory->create('TownDayQuestion', $citizen),
			$factory->create('TownNameQuestion', $citizen),
		];
	}
}

class TownDayQuestion extends CitizenQuestion {

	function getPrompt(): string {
		return 'Quel jour sommes-nous ?';
	}

	function getOptions(): array {
		$day = $this->citizen->getTown()->getDay();
		$day2 = $this->getDifferentAround($day, [], 1, null, 1, 7);
		$day3 = $this->getDifferentAround($day, [$day2], 1, null, 1, 7);

		return [
			"Jour {$day}",
			"Jour {$day2}",
			"Jour {$day3}",
		];
	}
}

class TownNameQuestion extends CitizenQuestion {

	function getPrompt(): string {
		return 'Quel est le nom de cette ville déjà ?';
	}

	function getOptions(): array {
		$town = $this->citizen->getTown();
		return [
			$town->getName(),
			$this->gameFactory->createTownName($town->getLanguage()),
			$this->gameFactory->createTownName($town->getLanguage()),
		];
	}
}
