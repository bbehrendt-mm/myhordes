<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class RateLimitingFactoryProvider
{
    private CamelCaseToSnakeCaseNameConverter $normalizer;

    public function __construct(
        #[Target('public_api')] public readonly RateLimiterFactoryInterface $publicApi,
        #[Target('anonymous_api')] public readonly RateLimiterFactoryInterface $anonymousApi,
        #[Target('authenticated_personal_api')] public readonly RateLimiterFactoryInterface $authenticatedPersonalApi,
        #[Target('authenticated_api')] public readonly RateLimiterFactoryInterface $authenticatedApi,
        #[Target('blackboard_edit_slide')] public readonly RateLimiterFactoryInterface $blackboardEditSlide,
        #[Target('blackboard_edit_fixed')] public readonly RateLimiterFactoryInterface $blackboardEditFixed,
        #[Target('forum_thread_creation')] public readonly RateLimiterFactoryInterface $forumThreadCreation,
        #[Target('report_to_moderation')] public readonly RateLimiterFactoryInterface $reportToModeration,
        #[Target('report_to_moderation_limited')] public readonly RateLimiterFactoryInterface $reportToModerationLimited,
        #[Target('report_to_gitlab')] public readonly RateLimiterFactoryInterface $reportToGitlab,
    ) {
        $this->normalizer = new CamelCaseToSnakeCaseNameConverter();
    }

    public function byKey( string $key ): ?RateLimiterFactory {
        try {
            $camelCase = $this->normalizer->denormalize( $key );
        } catch (\Throwable $t) {
            return null;
        }

        return $this->$camelCase ?? null;
    }

    public function reportLimiter( bool|User $limited = false ): RateLimiterFactoryInterface {
        if (is_a( $limited, User::class )) $limited = $limited->hasRoleFlag( User::USER_ROLE_LIMIT_MODERATION );
        return $limited ? $this->reportToModerationLimited : $this->reportToModeration;
    }
}
