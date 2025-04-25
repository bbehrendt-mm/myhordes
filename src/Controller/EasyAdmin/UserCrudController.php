<?php

namespace App\Controller\EasyAdmin;

use App\Annotations\GateKeeperProfile;
use App\Controller\EasyAdmin\Field\ImagePathField;
use App\Controller\EasyAdmin\Field\LinkField;
use App\Entity\Avatar;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[GateKeeperProfile('skip')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Account')
            ->setEntityLabelInPlural('Accounts')
            ->setEntityLabelInSingular(
                fn (?User $user, ?string $pageName) => $user ? $user->getName() : 'Account'
            )
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setLabel('ID')->hideWhenCreating()->setDisabled();
        yield ImagePathField::new('avatar')->setLabel('Avatar')->hideWhenCreating()->formatValue(function ($_, ?User $user) {
            $avatar = $user?->getAvatar();
            return $avatar ? $this->router->generate('app_web_avatar', [ 'uid' => $user->getId(), 'name' => $avatar->getFilename(), 'ext' => $avatar->getFormat() ], UrlGeneratorInterface::ABSOLUTE_URL) : null;
        });
        yield TextField::new('username')->setLabel('Benutzername')->setMaxLength(16);
        yield TextField::new('display_name')->setLabel('Anzeigename')->setMaxLength(32);
        yield EmailField::new('email')->setLabel('E-Mail');
        yield LinkField::new('eternalId')->setLabel('EternalTwin-Konto')->target('_blank')->formatValue(fn(?string $value) => $value ? "https://eternaltwin.org/users/$value" : null)->setDisabled();
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
