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

namespace BaksDev\Yandex\Support\Api\Questions\Get;

use BaksDev\Yandex\Market\Api\YandexMarket;
use Generator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Возвращает ваши чаты с покупателями.
 */
#[Autoconfigure(public: true)]  /* TODO: удалить !!! */
final class YandexGetQuestionsRequest extends YandexMarket
{
    /**
     * Получение вопросов о товарах продавца
     *
     * @see https://yandex.ru/dev/market/partner-api/doc/ru/reference/goods-questions/getGoodsQuestions
     *
     * @return Generator<YandexQuestionDTO>
     */
    public function findAll(): Generator|false
    {
        $data = [
            'limit' => 50,
        ];

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                sprintf('/v1/businesses/%s/goods-questions', $this->getBusiness()),
                [
                    'json' => ['limit' => 50],
                ],
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('yandex-support: Ошибка %s при получении вопросов о товарах продавца', $response->getStatusCode()),
                [self::class.':'.__LINE__, $content],
            );

            return false;
        }

        foreach($content['result']['questions'] as $item)
        {
            yield new YandexQuestionDTO($item);
        }
    }
}
