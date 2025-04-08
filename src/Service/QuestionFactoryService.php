<?php

namespace App\Service;

use App\Security\Captcha\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;

class QuestionFactoryService {
	public function __construct(
		private readonly GameFactory $gameFactory,
		private readonly EntityManagerInterface $entityManager,
		private readonly Packages $packages,
	) {}

	public function create(string $className, ...$args): Question {
		$className = "App\Security\Captcha\Question\\" . $className;
		return new $className(
			...array_merge($args, [
				$this->gameFactory,
				$this->entityManager,
				$this->packages,
			])
		);
	}
}