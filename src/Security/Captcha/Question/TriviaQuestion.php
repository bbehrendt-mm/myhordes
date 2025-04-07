<?php

namespace App\Security\Captcha\Question;

use App\Service\QuestionFactoryService;

/**
 * A Question with no particular context
 */
abstract class TriviaQuestion extends Question {
	static function getAll(
		QuestionFactoryService $factory,
	): array {
		return [
			$factory->create('ZombiesControlQuestion'),
		];
	}
}

class ZombiesControlQuestion extends TriviaQuestion {

	protected int $nbZombies;

	public function init() {
		$this->nbZombies = rand(1, 30);
	}

	function getPrompt(): string {
		$term = 'un seul zombie';
		if($this->nbZombies > 5) {
			$term = "une horde de {$this->nbZombies} zombies";
		} else if ($this->nbZombies > 1) {
			$term = "une meute de {$this->nbZombies} zombies";
		}
		return "Combien de points de contrôle sont exercés par {$term}.";
	}

	function getOptions(): array {
		$other1 = $this->getDifferentAround($this->nbZombies, [], 0, 30, 1, 5);
		$other2 = $this->getDifferentAround($this->nbZombies, [$other1], 0, 30, 1, 5);

		return [
			"{$this->nbZombies} points de contrôle",
			"{$other1} points de contrôle",
			"{$other2} points de contrôle",
		];
	}
}
