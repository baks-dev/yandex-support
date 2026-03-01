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

namespace BaksDev\Yandex\Support\Api\Review\Get\GetListReviews;

use BaksDev\Yandex\Market\Api\YandexMarket;
use DateTimeImmutable;
use Generator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class YandexGetListReviewsRequest extends YandexMarket
{
    /**
     * Начало периода. Не включительно.
     * Если параметр не указан, возвращается информация за 6 месяцев
     * до указанной в dateTimeTo даты.
     */
    private ?string $dateFrom = null;

    /**
     * Конец периода. Не включительно.
     * Если параметр не указан, используется текущая дата.
     */
    private ?string $dateTo = null;

    /**
     * Статус реакции на отзыв:
     * "ALL" — все отзывы.
     * "NEED_REACTION" — отзывы, на которые нужно ответить.
     */
    private string $status = "NEED_REACTION";

    /**  Публичный метод устанавливает дату начала периода. Не включительно. */
    public function dateFrom(DateTimeImmutable $dateFrom): self
    {
        $this->dateFrom = $dateFrom->format('Y-m-d\TH:i:sP');
        return $this;
    }

    /**  Публичный метод устанавливает дату конеца периода. Не включительно. */
    public function dateTo(DateTimeImmutable $dateTo): self
    {
        $this->dateTo = $dateTo->format('Y-m-d\TH:i:sP');
        return $this;
    }

    /**  Публичный метод устанавливает статус реакции на отзыв "ALL" — все отзывы. */
    public function statusAll(): self
    {
        $this->status = "ALL";
        return $this;
    }

    /** return query parameters */
    private function query(): array
    {
        return [

            /** Идентификатор страницы c результатами. */
            // 'page_token' => 'nextPageToken'

            /** Количество значений на одной странице. */
            // 'limit' => 20 // максимальное значение 20

        ];
    }

    /** return body */
    private function body(): array
    {
        return [

            /**
             * Начало периода. Не включительно.
             * Если параметр не указан, возвращается информация за 6 месяцев
             * до указанной в dateTimeTo даты.
             */
            "dateTimeFrom" => $this->dateFrom,

            /**
             * Конец периода. Не включительно.
             * Если параметр не указан, используется текущая дата.
             */
            "dateTimeTo" => $this->dateTo,

            /**
             * "ALL" — все отзывы.
             * "NEED_REACTION" — отзывы, на которые нужно ответить.
             * */
            "reactionStatus" => $this->status,

            /** Оценка товара. Max items: 5 */
            //            "ratingValues" => [0],

            /**
             * Фильтр по идентификатору модели товара.
             *
             * Получить идентификатор модели можно с помощью одного из запросов:
             * POST businesses/{businessId}/offer-mappings;
             * POST models.
             * Max items: 20
             * */
            //            "modelIds" => [0],

            /** Фильтр отзывов за баллы Плюса. */
            "paid" => false,

        ];
    }

    /**
     * Возвращает все отзывы о товарах продавца по указанным фильтрам.
     * Результаты возвращаются постранично, одна страница содержит не более 20 отзывов.
     * Отзывы расположены в порядке публикации, поэтому вы можете передавать определенный
     * идентификатор страницы в page_token, если вы получали его ранее.
     *
     * @see https://yandex.ru/dev/market/partner-api/doc/ru/reference/goods-feedback/getGoodsFeedbacks
     *
     * @return Generator<YandexReviewDTO>
     */
    public function findAll(): Generator
    {

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                sprintf('/businesses/%s/goods-feedback', $this->getBusiness()),
                [
                    'query' => $this->query(),
                    'json' => $this->body()
                ]
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            foreach($content['errors'] as $error)
            {
                $this->logger->critical(
                    sprintf(
                        'Ошибка получения отзывов. code: %s, message: %s',
                        $error['code'],
                        $error['message']
                    ),
                    [self::class.':'.__LINE__, $this->body()]
                );
            }

            return false;

        }


        foreach($content['result']['feedbacks'] as $item)
        {
            yield new YandexReviewDTO($item);
        }
    }
}
