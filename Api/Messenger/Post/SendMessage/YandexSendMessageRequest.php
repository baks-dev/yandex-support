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

namespace BaksDev\Yandex\Support\Api\Messenger\Post\SendMessage;

use BaksDev\Yandex\Market\Api\YandexMarket;


final class YandexSendMessageRequest extends YandexMarket
{
    /** Идентификатор чата. */
    private int $yandexChat;

    /** Текст сообщения. */
    private string $message;

    /** Метод принимает и устанавливает идентификатор чата */
    public function yandexChat(int|string $yandexChat): self
    {
        $this->yandexChat = (int) $yandexChat;
        return $this;
    }

    /** Метод принимает и устанавливает текст сообщения */
    public function message(string $text): self
    {
        $this->message = $text;
        return $this;
    }

    /** Возвращает массив с query параметрами */
    private function query(): array
    {
        return [
            'chatId' => $this->yandexChat,
        ];
    }

    /** Возвращает массив с body */
    private function body(): array
    {
        return [
            'message' => $this->message,
        ];
    }


    /**
     * Отправляет сообщение в чат с покупателем.
     *
     * @see https://yandex.ru/dev/market/partner-api/doc/ru/reference/chats/sendMessageToChat
     */
    public function send(): bool
    {
        if($this->isExecuteEnvironment() === false)
        {
            $this->logger->critical('Запрос может быть выполнен только в PROD окружении', [self::class.':'.__LINE__]);
            return true;
        }

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                sprintf('/businesses/%s/chats/message', $this->getBusiness()),
                [
                    "query" => $this->query(),
                    "json" => $this->body(),
                ],
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('yandex-support: ошибка %s отправки сообщения', $response->getStatusCode()),
                [self::class.':'.__LINE__, $content, $this->query(), $this->body()],
            );

            $error = current($content['errors']);

            if(str_contains(haystack: $error['message'], needle: 'closed'))
            {
                return true;
            }

            return false;
        }

        if(empty($content))
        {
            return false;
        }

        return true;
    }
}
