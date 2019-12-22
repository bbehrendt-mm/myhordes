<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserPendingValidation;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use Doctrine\Common\Collections\Collection;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 */
class PublicController extends AbstractController
{

    /**
     * @Route("jx/public/login", name="public_login")
     * @return Response
     */
    public function login(): Response
    {
        return $this->render( 'ajax/public/login.html.twig' );
    }

    /**
     * @Route("jx/public/register", name="public_register")
     * @return Response
     */
    public function register(): Response
    {
        return $this->render( 'ajax/public/register.html.twig' );
    }

    /**
     * @Route("jx/public/validate", name="public_validate")
     * @return Response
     */
    public function validate(): Response
    {
        return $this->render( 'ajax/public/validate.html.twig' );
    }

    protected function create_user(UserPasswordEncoderInterface $passwordEncoder, string $name, string $mail, string $pass, bool $validate = true): ?User {
        $validator = Validation::createValidator();

        $manager = $this->getDoctrine()->getManager();

        $new_user = new User();
        $new_user->setName( $name )->setEmail( $mail )->setPassword( $passwordEncoder->encodePassword($new_user, $pass) )->setValidated( !$validate );

        if ($validator->validate($new_user)->count() > 0)
            return null;

        $manager->persist( $new_user );

        if ($validate) {
            $source = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $key = "";
            for ($i = 0; $i < 16; $i++) $key .= $source[ mt_rand(0, strlen($source) - 1) ];

            $new_validation = new UserPendingValidation();
            $new_validation->setPkey( $key );
            $new_validation->setUser( $new_user );

            if ($validator->validate($new_validation)->count() > 0)
                return null;

            $manager->persist( $new_validation );
        }

        try {
            $manager->flush();
        } catch (\Exception $e) {
            return null;
        }

        return $new_user;
    }

    /**
     * @Route("api/public/register", name="api_register")
     * @param JSONRequestParser $parser
     * @param TranslatorInterface $translator
     * @param Locksmith $locks
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return Response
     */
    public function register_api(
        JSONRequestParser $parser,
        TranslatorInterface $translator,
        Locksmith $locks,
        UserPasswordEncoderInterface $passwordEncoder
    ): Response
    {
        if (!$parser->valid()) return new JsonResponse( ['error' => 'json_malformed'] );
        if (!$parser->has_all( ['user','mail1','mail2','pass1','pass2'], true ))
            return new JsonResponse( ['error' => 'json_malformed'] );

        $violations = Validation::createValidator()->validate( $parser->all( true ), new Constraints\Collection([
            'user'  => new Constraints\Length(
                ['min' => 4, 'max' => 16,
                    'minMessage' => $translator->trans('Dein Name muss mindestens {{ limit }} Zeichen umfassen.', [], 'login'),
                    'maxMessage' => $translator->trans('Dein Name kann höchstens {{ limit }} Zeichen umfassen.', [], 'login'),
                ]),
            'mail1' => new Constraints\Email(
                ['message' => $translator->trans('Die eingegebene E-Mail Adresse ist nicht gültig.', [], 'login')]),
            'mail2' => new Constraints\EqualTo(
                ['value' => $parser->trimmed( 'mail1'), 'message' => $translator->trans('Die eingegebenen E-Mail Adressen stimmen nicht überein.', [], 'login')]),
            'pass1' => new Constraints\Length(
                ['min' => 6, 'minMessage' => $translator->trans('Dein Passwort muss mindestens {{ limit }} Zeichen umfassen.', [], 'login')]),
            'pass2' => new Constraints\EqualTo(
                ['value' => $parser->trimmed( 'pass1' ), 'message' => $translator->trans('Die eingegebenen Passwörter stimmen nicht überein.', [], 'login')]),
        ]) );

        if ($violations->count() === 0) {

            $lock = $locks->waitForLock( 'user-creation' );

            if ($this->getDoctrine()->getRepository(User::class)->findOneByName( $parser->trimmed('user') ))
                return new JsonResponse( ['error' => 'user_exists'] );
            if ($this->getDoctrine()->getRepository(User::class)->findOneByMail( $parser->trimmed('mail1') ))
                return new JsonResponse( ['error' => 'mail_exists'] );

            if (!($user = $this->create_user(
                $passwordEncoder,
                $parser->trimmed('user'),
                $parser->trimmed('mail1'),
                $parser->trimmed('pass1'),
                true
            ))) return new JsonResponse( ['error' => 'db_error'] );

            $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
            $this->get('security.token_storage')->setToken($token);

            return new JsonResponse( ['success' => 'validation'] );

        } else {
            $v = [];
            foreach ($violations as &$violation)
                /** @var ConstraintViolationInterface $violation */
                $v[] = $violation->getMessage();

            return new JsonResponse( ['error' => 'invalid_fields', 'fields' => $v] );
        }
    }

    /**
     * @Route("api/public/login", name="api_login")
     * @return Response
     */
    public function login_api(

    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user)
            return new JsonResponse( ['success' => false ] );
        if (!$user->getValidated())
            return new JsonResponse( ['success' => true, 'require_validation' => true ] );
        else return new JsonResponse( ['success' => true, 'require_validation' => false ] );
    }

    /**
     * @Route("api/public/validate", name="api_validate")
     * @param JSONRequestParser $parser
     * @param TranslatorInterface $translator
     * @param Locksmith $locks
     * @return Response
     */
    public function validate_api(
        JSONRequestParser $parser,
        TranslatorInterface $translator,
        Locksmith $locks
    ): Response
    {
        if (!$parser->valid()) return new JsonResponse( ['error' => 'json_malformed'] );
        if (!$parser->has_all( ['validate'], true ))
            return new JsonResponse( ['error' => 'json_malformed'] );

        $violations = Validation::createValidator()->validate( $parser->all( true ), new Constraints\Collection([
            'validate'  => new Constraints\Length(
                ['min' => 16, 'max' => 16,
                    'exactMessage' => $translator->trans('Der Validierungscode muss {{ limit }} Zeichen umfassen.', [], 'login'),
                ])
        ]) );

        if ($violations->count() === 0) {

            $lock = $locks->waitForLock( 'user-creation' );

            /** @var User $user */
            $user = $this->getUser();

            if (($pending = $this->getDoctrine()->getRepository(UserPendingValidation::class)->findOneByTokenAndUser(
                $parser->trimmed('validate'), $user
            )) === null) return new JsonResponse( ['error' => 'token_invalid'] );

            if ($pending->getUser() === null || ($user !== null && !$user->isEqualTo( $pending->getUser() )))
                return new JsonResponse( ['error' => 'token_invalid'] );

            if ($user === null) $user = $pending->getUser();

            try {
                $entityManager = $this->getDoctrine()->getManager();

                $user->setValidated( true );
                $entityManager->persist( $user );
                $entityManager->remove( $pending );
                $entityManager->flush();

                if ($this->getUser()) {
                    $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
                    $this->get('security.token_storage')->setToken($token);
                }

                return new JsonResponse( ['success' => true] );
            } catch (Exception $e) {
                return new JsonResponse( ['error' => 'db_error'] );
            }

        } else {
            $v = [];
            foreach ($violations as &$violation)
                /** @var ConstraintViolationInterface $violation */
                $v[] = $violation->getMessage();

            return new JsonResponse( ['error' => 'invalid_fields', 'fields' => $v] );
        }
    }

    /**
     * @Route("jx/public/welcome", name="public_welcome")
     * @return Response
     */
    public function welcome(): Response
    {
        return $this->render( 'ajax/public/intro.html.twig' );
    }

}
