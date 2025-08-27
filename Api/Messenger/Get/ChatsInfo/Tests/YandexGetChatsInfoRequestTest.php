<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Yandex\Support\Api\Messenger\Get\ChatsInfo\Tests;

use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Yandex\Market\Type\Authorization\YaMarketAuthorizationToken;
use BaksDev\Yandex\Support\Api\Messenger\Get\ChatsInfo\YandexChatsDTO;
use BaksDev\Yandex\Support\Api\Messenger\Get\ChatsInfo\YandexGetChatsInfoRequest;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group yandex-support
 *
 */
#[When(env: 'test')]
class YandexGetChatsInfoRequestTest extends KernelTestCase
{
    private static YaMarketAuthorizationToken $authorization;

    public static function setUpBeforeClass(): void
    {
        self::$authorization = new YaMarketAuthorizationToken(
            profile: UserProfileUid::TEST,
            token: $_SERVER['TEST_YANDEX_MARKET_TOKEN'],
            company: (int) $_SERVER['TEST_YANDEX_MARKET_COMPANY'],
            business: (int) $_SERVER['TEST_YANDEX_MARKET_BUSINESS'],
            card: false,
            stocks: false,
        );
    }

    public function testComplete(): void
    {

        //        /** @var AppCacheInterface $cache */
        //        $initCache = self::getContainer()->get(AppCacheInterface::class);
        //        $cache = $initCache->init('avito-support');

        //        $cache->delete(sprintf('%s-%s', 'avito-support-info-chats', UserProfileUid::TEST));

        /** @var YandexGetChatsInfoRequest $YandexGetChatsInfoRequest */
        $YandexGetChatsInfoRequest = self::getContainer()->get(YandexGetChatsInfoRequest::class);
        $YandexGetChatsInfoRequest->tokenHttpClient(self::$authorization);

        $chatsInfo = $YandexGetChatsInfoRequest->findAll();


        dd(iterator_to_array($chatsInfo));

        if($chatsInfo->valid())
        {
            /** @var YandexChatsDTO $YandexChatsDTO */
            $YandexChatsDTO = $chatsInfo->current();

            self::assertNotNull($YandexChatsDTO->getId());
            self::assertIsInt($YandexChatsDTO->getId());

            self::assertNotNull($YandexChatsDTO->getType());
            self::assertIsString($YandexChatsDTO->getType());

            self::assertNotNull($YandexChatsDTO->getOrder());
            self::assertIsInt($YandexChatsDTO->getOrder());

            self::assertNotNull($YandexChatsDTO->getStatus());
            self::assertIsString($YandexChatsDTO->getStatus());

            self::assertNotNull($YandexChatsDTO->getCreated());
            self::assertInstanceOf(
                DateTimeImmutable::class,
                $YandexChatsDTO->getCreated()
            );

            self::assertNotNull($YandexChatsDTO->getUpdated());
            self::assertInstanceOf(
                DateTimeImmutable::class,
                $YandexChatsDTO->getUpdated()
            );
        }
        else
        {
            self::assertFalse($chatsInfo->valid());
        }
    }
}
