<?php

namespace App\Security\Captcha\Question;

use App\Service\GameFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;

/**
 * A question that can be asked in the Captcha
 */
abstract class Question {

	/**
	 * @see QuestionFactoryService for adding autowired services
	 */
	public function __construct(
        protected readonly GameFactory $gameFactory,
		protected readonly EntityManagerInterface $entityManager,
		protected readonly Packages $packages,
	) {
		$this->init();
	}

	/**
	 * Called after the base constructor for initialization purposes
	 */
	function init() {
		// Initialize the question if needed
	}

	/**
	 * Return the prompt for this question
	 */
	abstract function getPrompt(): string;

	/**
	 * Optionally set an icon to be displayed next to the prompt
	 */
	function getPromptIcon(): string {
		return '';
	}

	/**
	 * Optionally set a context to ensure a human doesn't get stuck
	 */
	function getPromptContext(): string {
		return '';
	}

	/**
	 * Return the available answers for the prompt. The first one should be the correct answer.
	 * @return string[]
	 */
	abstract function getOptions(): array;

	/**
	 * Util function to get a random number under certain conditions.
	 * If the conditions are impossible to respect, they may not be respected but the function will return a number.
	 *
	 * @param int $around The starting point for the random number (default: 0)
	 * @param array $different An array of already used numbers (default: [])
	 * @param int $min The minimum value for the random number (default: null)
	 * @param int $max The maximum value for the random number (default: null)
	 * @param int $minDiff The minimum difference between two random numbers (default: 1)
	 * @param int $maxDiff The maximum difference between two random numbers (default: 10)
	 */
	protected function getDifferentAround(int $around = 0, array $different = [], ?int $min = null, ?int $max = null, int $minDiff = 1, int $maxDiff = 10) {
		$maxTries = 1000; // Prevent from searching indefinitely at the cost of respecting the constraints
		$tries = 0;
		do {
			$dist = rand($minDiff, $maxDiff);
			$n = $around + $dist;

			if($tries > $maxTries) break;
			if($n == $around) continue;
			if($n < $min) continue;
			if($n > $max) continue;
		} while(in_array($n, $different));

		return $n;
	}

	/**
	 * Util function to get a random element from and array, and removes this element from said array
	 * @param array The array to retrieve and remove a random element from
	 */
	protected function shiftRandomFromArray(array &$array) {
		if (empty($array)) {
			return null;
		}

		$i = array_rand($array);
		$element = $array[$i];
		unset($array[$i]);

		return $element;
	}
}