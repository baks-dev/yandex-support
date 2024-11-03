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

namespace BaksDev\Yandex\Support\Api\Messenger\Get\ListMessages;

use BaksDev\Yandex\Market\Api\YandexMarket;
use Generator;


final class YandexGetListMessagesRequest extends YandexMarket
{
    /** Идентификатор чата */
    private int $chatId;

    /** Метод принимает и устанавливает идентификатор чата */
    public function chat(int|string $chatId): self
    {
        $this->chatId = (int) $chatId;
        return $this;
    }


    /** Возвращает массив с query параметрами */
    private function query(): array
    {
        return [
            /**
             * Идентификатор страницы c результатами.
             * Example: eyBuZXh0SWQ6IDIzNDIgfQ==
             */
            // 'page_token' => 'nextPageToken'

            /** Количество значений на одной странице. */
            // 'limit' => 50,

            /** Идентификатор чата. */
            'chatId' => $this->chatId
        ];
    }

    /**
     * Возвращает ваши чаты с покупателями.
     *
     * @see https://yandex.ru/dev/market/partner-api/doc/ru/reference/chats/getChatHistory
     */
    public function findAll(): Generator|false
    {

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                sprintf('/businesses/%s/chats/history', $this->getBusiness()),
                [
                    'query' => $this->query(),
                    'json' => [
                        'messageIdFrom' => 1    // Идентификатор сообщения, начиная с которого
                        // нужно получить все последующие сообщения.
                    ]
                ],

            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {

            foreach($content['errors'] as $error)
                $this->logger->critical(
                    sprintf('yandex-support:%s, %s', $error['code'], $error['message']),
                    [self::class.':'.__LINE__, 'chatId' => $this->chatId]
                );

            return false;
        }


        foreach($content['result']['messages'] as $item)
        {
            yield new YandexListMessagesDTO($content['result']['orderId'], $item);
        }
    }
}
