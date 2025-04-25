<?php

namespace App\Controller\EasyAdmin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Contracts\Translation\TranslatableInterface;


class LinkField implements FieldInterface
{
    use FieldTrait;

    public const string OPTION_TARGET = 'target';

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/fields/link.html.twig')
            ->setFormType(UrlType::class)
            ->addCssClass('field-text')
            ->setDefaultColumns('col-md-6 col-xxl-5')
            ->setCustomOption(self::OPTION_TARGET, '_self');
    }

    public function target(string $target = '_self'): self
    {
        $this->setCustomOption(self::OPTION_TARGET, $target);

        return $this;
    }

}
