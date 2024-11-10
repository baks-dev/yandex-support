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

namespace BaksDev\Yandex\Support\Api\Messenger\Get\ChatsInfo;

use BaksDev\Yandex\Market\Api\YandexMarket;
use Generator;

/**
 * Возвращает ваши чаты с покупателями.
 */
final class YandexGetChatsInfoRequest extends YandexMarket
{
    /** Фильтр по типам чатов. */
    private array $types = [
        'CHAT',         // чат с покупателем.
        'ARBITRAGE'     // спор
    ];

    /** Фильтр по типам чатов. Тип чата: */
    private array $statuses = [
        'NEW',                  // новый чат.
        'WAITING_FOR_PARTNER',  // нужен ответ магазина.
        // 'WAITING_FOR_CUSTOMER', // нужен ответ покупателя.
        // 'WAITING_FOR_ARBITER',  // нужен ответ арбитра.
        // 'WAITING_FOR_MARKET',   // нужен ответ Маркета.
        // 'FINISHED'              // чат завершен.
    ];

    /** Метод фильтрует чаты по типам (принимает массив типов)*/
    public function types(array $types): self
    {
        $this->types = $types;
        return $this;
    }

    /** Метод фильтрует чаты по статусам (принимает массив статусов)*/
    public function statuses(array $statuses): self
    {
        $this->statuses = $statuses;
        return $this;
    }

    /** Возвращает массив с query параметрами */
    private function query(): array
    {
        return [

            /** Идентификатор страницы c результатами.*/
            // 'page_token' => 'nextPageToken'

            /** Количество значений на одной странице. */
            // 'limit' => 50

        ];
    }

    /** Возвращает массив с body */
    private function body(): array
    {
        return [
            "types" => $this->types,
            "statuses" => $this->statuses
        ];
    }

    /**
     * Возвращает ваши чаты с покупателями.
     *
     * @see https://yandex.ru/dev/market/partner-api/doc/ru/reference/chats/getChats
     */
    public function findAll(): Generator|false
    {

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                sprintf('/businesses/%s/chats', $this->getBusiness()),
                [
                    'query' => $this->query(),
                    'json' => $this->body()
                ],

            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {

            foreach($content['errors'] as $error)
                $this->logger->critical(
                    sprintf('yandex-support:%s, %s', $error['code'], $error['message']),
                    [self::class.':'.__LINE__, $this->body()],
                );

            return false;
        }


        foreach($content['result']['chats'] as $item)
        {
            yield new YandexChatsDTO($item);
        }
    }
}
