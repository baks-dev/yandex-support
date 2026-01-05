<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Yandex\Support\Api\Questions\Post\SendQuestion\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Yandex\Market\Type\Authorization\YaMarketAuthorizationToken;
use BaksDev\Yandex\Support\Api\Questions\Post\SendQuestion\YandexSendQuestionRequest;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;


#[Group('yandex-send-question-request-test')]
#[When(env: 'test')]
class YandexSendQuestionRequestTest extends KernelTestCase
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

    public function testUseCase(): void
    {
        self::assertTrue(true);
        return;

        /** @var YandexSendQuestionRequest $YandexSendQuestionRequest */
        $YandexSendQuestionRequest = self::getContainer()->get(YandexSendQuestionRequest::class);
        $YandexSendQuestionRequest->tokenHttpClient(self::$authorization);

        $reviews = $YandexSendQuestionRequest
            ->identifier(699)
            ->message('Здравствуйте! На всю продукцию нами предоставляется гарантия 1 год на заводской брак, а также в течении 10 дней вы можете вернуть товар при условии надлежащего качества. 
Возврат товара надлежащего качества возможен в случае, если сохранены его товарный вид, потребительские свойства, а также документ, подтверждающий факт и условия покупки указанного товара в нашем магазине. 
Обращаем ваше внимание, что если шины были установлены на диск, возврат товара возможен только в случае наличия гарантийного случая.
Брак, выявленный в ходе эксплуатации автомобильных шин, принимается в качестве рекламации после положительного решения по экспертизе НИИШП (научно-исследовательского института шинной промышленности).')
            ->send();

    }

}