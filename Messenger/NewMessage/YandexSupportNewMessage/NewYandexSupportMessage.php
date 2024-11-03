<?php

declare(strict_types=1);

namespace BaksDev\Yandex\Support\Messenger\NewMessage\YandexSupportNewMessage;

use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see NewYandexSupportReviewMessage */
final class NewYandexSupportMessage
{
    /** Идентификатор */
    #[Assert\Uuid]
    private UserProfileUid $profile;

    public function __construct(UserProfileUid|string $profile)
    {

        $this->profile = $profile;
    }

    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

}