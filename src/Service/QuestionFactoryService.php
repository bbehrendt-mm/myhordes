<?php

namespace App\Service;

use App\Security\Captcha\Question\Question;

class QuestionFactoryService {
	public function __construct(
		private readonly GameFactory $gameFactory,
	) {}

	public function create(string $className, ...$args): Question {
		$className = "App\Security\Captcha\Question\\" . $className;
		return new $className(
			...array_merge($args, [
				$this->gameFactory
			])
		);
	}
}