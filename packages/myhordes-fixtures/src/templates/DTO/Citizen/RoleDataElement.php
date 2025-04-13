<?php

namespace MyHordes\Fixtures\DTO\Citizen;

use App\Entity\AwardPrototype;
use App\Entity\CitizenRole;
use App\Entity\PictoPrototype;
use Doctrine\ORM\EntityManagerInterface;
use MyHordes\Fixtures\DTO\Element;

/**
 * @property string $name
 * @method self name(string $v)
 * @property string $label
 * @method self label(string $v)
 * @property string $icon
 * @method self icon(string $v)
 * @property bool $vote
 * @method self vote(bool $v)
 * @property bool $hidden
 * @method self hidden(bool $v)
 * @property bool $secret
 * @method self secret(bool $v)
 * @property bool $notShunned
 * @method self notShunned(bool $v)
 * @property string $help
 * @method self help(string $v)
 * @property string $message
 * @method self message(string $v)
 *
 * @method RoleDataContainer commit(string &$id = null)
 * @method RoleDataContainer discard()
 */
class RoleDataElement extends Element {

    /**
     * @throws \Exception
     */
    public function toEntity(CitizenRole $entity): void {
        $entity
            ->setName($this->name)
            ->setIcon($this->icon ?? $this->name)
            ->setLabel($this->label)
            ->setMessage($this->message)
            ->setHelpSection($this->help)
            ->setVotable($this->vote ?? false)
            ->setHidden($this->hidden ?? false)
            ->setSecret($this->secret ?? false)
            ->setDisallowShunned($this->notShunned ?? false);
    }

}