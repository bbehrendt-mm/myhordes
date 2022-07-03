<?php

namespace App\Interfaces;

interface NamedEntity
{
    public function getName(): ?string;
    public function getLabel(): ?string;
    public static function getTranslationDomain(): ?string;
}
