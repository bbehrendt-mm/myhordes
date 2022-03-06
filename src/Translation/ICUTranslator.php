<?php

namespace App\Translation;

use App\Entity\Citizen;
use App\Entity\User;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ICUTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    private TranslatorInterface $_decorated;
    private Security $_security;

    public function __construct(TranslatorInterface $translator, Security $security) {
        $this->_decorated = $translator;
        $this->_security = $security;
    }

    static array $gender_map = [ 0 => 'none', 1 => 'male', 2 => 'female' ];

    public function trans(?string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        /** @var User $u */
        $u = $this->_security->getUser();
        $pass_trough = [
            'ref__icu' => $u ? ($u->getUseICU() ? 'on' : 'off') : 'off',
            'ref__gender' => 'none'
        ];

        $got_citizen = false;

        foreach ($parameters as $key => $value) {
            $key = str_replace(['{','}'],'', $key);
            if (is_a( $value, User::class )) {
                /** @var User $value */
                $pass_trough["{$key}__gender"] = static::$gender_map[(int)$value->getPreferredPronoun()];
                if (!$got_citizen) {
                    $pass_trough["ref__gender"] = $pass_trough["{$key}__gender"];
                    $got_citizen = true;
                }
                $pass_trough[$key] = $value->getName();
            } elseif (is_a( $value, Citizen::class )) {
                /** @var Citizen $value */
                $pass_trough["{$key}__gender"] = static::$gender_map[(int)$value->getUser()->getPreferredPronoun()];
                if (!$got_citizen) {
                    $pass_trough["ref__gender"] = $pass_trough["{$key}__gender"];
                    $got_citizen = true;
                }
                $pass_trough[$key] = $value->getName();
            } else {
                $pass_trough[$key] = $value;
                $pass_trough["{$key}__copy"] = $value;
            }

            if (isset($parameters["{$key}__tag"])) $pass_trough[$key] = "<{$parameters["{$key}__tag"]} class=\"" . ($parameters["{$key}__class"] ?? '') . "\">{$pass_trough[$key]}</{$parameters["{$key}__tag"]}>";
        }

        return $this->_decorated->trans($id,$pass_trough,$domain,$locale);
    }

    public function getCatalogue(string $locale = null): MessageCatalogueInterface
    {
        return $this->_decorated->getCatalogue($locale);
    }

    public function setLocale(string $locale)
    {
        $this->_decorated->setLocale($locale);
    }

    public function getLocale(): string {
        return $this->_decorated->getLocale();
    }

    public function getCatalogues(): array
    {
        return $this->_decorated->getCatalogues();
    }
}
