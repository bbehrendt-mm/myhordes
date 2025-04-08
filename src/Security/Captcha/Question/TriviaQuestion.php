<?php

namespace App\Security\Captcha\Question;

use App\Entity\CitizenProfession;
use App\Entity\ItemPrototype;
use App\Service\QuestionFactoryService;

/**
 * A Question with no particular context
 */
abstract class TriviaQuestion extends Question {
	static function getAll(
		QuestionFactoryService $factory,
	): array {
		return [
			// $factory->create('ZombiesControlQuestion'),
			// $factory->create('JobQuestion'),
			$factory->create('ItemQuestion'),
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

class JobQuestion extends TriviaQuestion {

	protected CitizenProfession $prof1;
	protected CitizenProfession $prof2;
	protected CitizenProfession $prof3;

	public function init() {
		$professions = $this->entityManager->getRepository(CitizenProfession::class)->findAll();

		$this->prof1 = $this->shiftRandomFromArray($professions);
		$this->prof2 = $this->shiftRandomFromArray($professions);
		$this->prof3 = $this->shiftRandomFromArray($professions);
	}

	function getPrompt(): string {
		return "À quel métier correspond cet icône ?";
	}

	function getPromptIcon(): string {
		return $this->packages->getUrl( "build/images/professions/{$this->prof1->getIcon()}.gif" );
	}

	function getPromptContext(): string {
		return $this->prof1->getDescription();
	}

	function getOptions(): array {
		return [
			$this->prof1->getLabel(),
			$this->prof2->getLabel(),
			$this->prof3->getLabel(),
		];
	}
}


class ItemQuestion extends TriviaQuestion {

	protected ItemPrototype $item1;
	protected ItemPrototype $item2;
	protected ItemPrototype $item3;

	public function init() {
		$items = $this->entityManager->getRepository(ItemPrototype::class)->findAll();

		$items_unique_icon = [];
		foreach($items as $item) {
			if(!isset($items_unique_icon[$item->getIcon()])) {
				$items_unique_icon[$item->getIcon()] = $item;
			}
		}

		$items_unique_label = [];
		foreach($items_unique_icon as $item) {
			if(!isset($items_unique_label[$item->getLabel()])) {
				$items_unique_label[$item->getLabel()] = $item;
			}
		}

		$this->item1 = $this->shiftRandomFromArray($items_unique_label);
		$this->item2 = $this->shiftRandomFromArray($items_unique_label);
		$this->item3 = $this->shiftRandomFromArray($items_unique_label);
	}

	function getPrompt(): string {
		return "Quel est cet objet ?";
	}

	function getPromptContext(): string {
		return $this->item1->getDescription();
	}

	function getPromptIcon(): string {
		return $this->packages->getUrl( "build/images/item/item_{$this->item1->getIcon()}.gif" );
	}

	function getOptions(): array {
		return [
			$this->item1->getLabel(),
			$this->item2->getLabel(),
			$this->item3->getLabel(),
		];
	}
}
