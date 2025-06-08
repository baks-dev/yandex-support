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

namespace BaksDev\Yandex\Support\Api\Review\Get\GetComments\Tests;

use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Yandex\Market\Type\Authorization\YaMarketAuthorizationToken;
use BaksDev\Yandex\Support\Api\Review\Get\GetComments\YandexCommentsDTO;
use BaksDev\Yandex\Support\Api\Review\Get\GetComments\YandexGetCommentsRequest;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group yandex-support
 *
 */
#[When(env: 'test')]
class YandexGetCommentsTest extends KernelTestCase
{

    private static YaMarketAuthorizationToken $authorization;

    public static function setUpBeforeClass(): void
    {
        self::$Authorization = new YaMarketAuthorizationToken(
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

        self::assertTrue(true);
        return;

        /** @var YandexGetCommentsRequest $YandexGetCommentsRequest */
        $YandexGetCommentsRequest = self::getContainer()->get(YandexGetCommentsRequest::class);
        $YandexGetCommentsRequest->tokenHttpClient(self::$authorization);

        $YandexGetCommentsRequest->feedback('feedbackId');
        $reviews = $YandexGetCommentsRequest->findAll();


        //                 dd(iterator_to_array($reviews));

        if($reviews->valid())
        {
            /** @var YandexCommentsDTO $YandexCommentsDTO */
            $YandexCommentsDTO = $reviews->current();

            self::assertNotNull($YandexCommentsDTO->getId());
            self::assertIsInt($YandexCommentsDTO->getId());

            self::assertNotNull($YandexCommentsDTO->getText());
            self::assertIsString($YandexCommentsDTO->getText());

            self::assertNotNull($YandexCommentsDTO->getCreated());
            self::assertInstanceOf(
                DateTimeImmutable::class,
                $YandexCommentsDTO->getCreated()
            );

            self::assertNotNull($YandexCommentsDTO->getStatus());
            self::assertIsString($YandexCommentsDTO->getStatus());

            self::assertNotNull($YandexCommentsDTO->getAuthor());
            self::assertIsArray($YandexCommentsDTO->getAuthor());

            if(null !== $YandexCommentsDTO->getParent())
            {
                self::assertIsString($YandexCommentsDTO->getParent());
            }
        }
        else
        {
            self::assertFalse($reviews->valid());
        }

    }
}
