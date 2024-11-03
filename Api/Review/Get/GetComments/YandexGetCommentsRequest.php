<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Yandex\Support\Api\Review\Get\GetComments;

use BaksDev\Yandex\Market\Api\YandexMarket;
use Generator;


final class YandexGetCommentsRequest extends YandexMarket
{
    /**  Идентификатор отзыва. */
    private int $feedback;

    /**  Публичный метод устанавливает идентификатор отзыва */
    public function feedback(string|int $feedback): self
    {
        $this->feedback = (int) $feedback;
        return $this;
    }

    /** return query parameters */
    private function query(): array
    {
        return [

            /** Идентификатор страницы c результатами.*/
            // 'page_token' => 'nextPageToken'
            /** Количество значений на одной странице. */
            // 'limit' => 20 // максимальное значение 20

        ];
    }


    /**
     * Возвращает комментарии к отзыву.
     * @see https://yandex.ru/dev/market/partner-api/doc/ru/reference/goods-feedback/getGoodsFeedbackComments
     */
    public function findAll(): Generator
    {

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                sprintf('/businesses/%s/goods-feedback/comments', $this->getBusiness()),
                [
                    'query' => $this->query(),
                    'json' => [
                        "feedbackId" => $this->feedback,
                    ]
                ]
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf(
                    'Ошибка получения отзывов. code: %s, message: %s',
                    $content['error']['code'],
                    $content['error']['message']
                ),
                [self::class.':'.__LINE__]
            );

            return false;
        }


        foreach($content['result']['comments'] as $item)
        {
            yield new YandexCommentsDTO($item);
        }
    }
}
