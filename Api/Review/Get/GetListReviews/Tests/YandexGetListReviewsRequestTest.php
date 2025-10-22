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

namespace BaksDev\Yandex\Support\Api\Review\Get\GetListReviews\Tests;

use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Yandex\Market\Type\Authorization\YaMarketAuthorizationToken;
use BaksDev\Yandex\Support\Api\Review\Get\GetListReviews\YandexGetListReviewsRequest;
use BaksDev\Yandex\Support\Api\Review\Get\GetListReviews\YandexReviewDTO;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('yandex-support')]
class YandexGetListReviewsRequestTest extends KernelTestCase
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
        self::assertTrue(true);

        /** @var YandexGetListReviewsRequest $YandexGetListReviewsRequest */
        $YandexGetListReviewsRequest = self::getContainer()->get(YandexGetListReviewsRequest::class);
        $YandexGetListReviewsRequest->tokenHttpClient(self::$authorization);

        $YandexGetListReviewsRequest->dateFrom(new DateTimeImmutable('1 day ago'));
        $YandexGetListReviewsRequest->statusAll();

        $reviews = $YandexGetListReviewsRequest->findAll();

        if(false === $reviews->valid())
        {
            return;
        }

        foreach($reviews as $YandexReviewDTO)
        {
            // Вызываем все геттеры
            $reflectionClass = new ReflectionClass(YandexReviewDTO::class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($YandexReviewDTO);
                    // dump($data);
                }
            }

            break;
        }
    }
}
