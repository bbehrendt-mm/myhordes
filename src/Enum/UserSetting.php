<?php

namespace App\Enum;

enum UserSetting: string {
    case Flag                           = 'country-flag';
    case NoAutomaticNameManagement      = 'et-no-auto-names';
    case ClassicBankSort                = 'classic-bank-sort';
    case NoAutomaticThreadSubscription  = 'no-auto-follow-threads';
    case OpenDashboardInSameWindow      = 'mod-open-dash-same-window';
    case PreferredPronoun               = 'preferred-pronoun';
    case UseICU                         = 'use-icu';
    case UseExpertMode                  = 'use-expert-mode';
    case DisableEffects                 = 'disable-fx';
    case PostAs                         = 'mod-post-as';
    case PreferSmallAvatars             = 'prefer-small-avatars';
    case LimitTownListSize              = 'limit-town-lists';
    case NotifyMeWhenMentioned          = 'notify-on-mention-mode';
    case NotifyMeOnFriendRequest        = 'notify-on-friend-request';
    case ReorderActionButtonsBeyond     = 'reorder-action-buttons-beyond';

    /**
     * @return UserSetting[]
     */
    public static function migrateCases(): array {
        return [
            self::Flag, self::NoAutomaticNameManagement, self::ClassicBankSort, self::NoAutomaticThreadSubscription,
            self::OpenDashboardInSameWindow, self::PreferredPronoun, self::UseICU, self::UseExpertMode,
            self::DisableEffects, self::PostAs, self::PreferSmallAvatars
        ];
    }

    public function defaultValue(): int|bool|null
    {
        /** @noinspection PhpDuplicateMatchArmBodyInspection */
        return match ($this) {
            UserSetting::Flag                          => null,
            UserSetting::NoAutomaticNameManagement     => false,
            UserSetting::ClassicBankSort               => false,
            UserSetting::NoAutomaticThreadSubscription => false,
            UserSetting::OpenDashboardInSameWindow     => false,
            UserSetting::PreferredPronoun              => 0,
            UserSetting::UseICU                        => false,
            UserSetting::UseExpertMode                 => false,
            UserSetting::DisableEffects                => false,
            UserSetting::PostAs                        => null,
            UserSetting::PreferSmallAvatars            => false,
            UserSetting::LimitTownListSize             => true,
            UserSetting::NotifyMeWhenMentioned         => 0, // 0 = Disabled, 1 = Towns Only, 2 = Everywhere, 3 = Global Only
            UserSetting::NotifyMeOnFriendRequest       => true,
            UserSetting::ReorderActionButtonsBeyond    => false,
        };
    }
}