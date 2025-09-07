<?php

namespace App\Console;

use Symfony\Component\Console\Question\ChoiceQuestion;

class AssociativeChoiceQuestion extends ChoiceQuestion
{
    protected bool $associative = true;

    protected function isAssoc(array $array): bool
    {
        return $this->associative;
    }

    public function setAssociative(bool $assoc = true): static
    {
        $this->associative = $assoc;
        return $this;
    }
}