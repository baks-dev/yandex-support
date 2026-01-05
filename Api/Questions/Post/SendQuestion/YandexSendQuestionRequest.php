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

namespace BaksDev\Yandex\Support\Api\Questions\Post\SendQuestion;

use BaksDev\Yandex\Market\Api\YandexMarket;

final class YandexSendQuestionRequest extends YandexMarket
{
    /** Идентификатор вопроса. */
    private int $id;

    /** Текст сообщения. */
    private string $message;

    public function identifier(int|string $id): self
    {
        $this->id = (int) $id;
        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;
        return $this;
    }


    /**
     * Создание ответа на вопрос
     *
     * @see https://yandex.ru/dev/market/partner-api/doc/ru/reference/goods-questions/updateGoodsQuestionTextEntity
     */
    public function send(): bool
    {
        if($this->isExecuteEnvironment() === false)
        {
            $this->logger->critical('Запрос может быть выполнен только в PROD окружении', [self::class.':'.__LINE__]);
            return true;
        }

        $data = [
            'operationType' => 'CREATE',
            'parentEntityId' => ['id' => $this->id, 'type' => 'QUESTION'],
            'text' => $this->message,
        ];

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                sprintf('/v1/businesses/%s/goods-questions/update', $this->getBusiness()),
                ["json" => $data],
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('yandex-support: ошибка %s отправки ответа на вопрос', $response->getStatusCode()),
                [self::class.':'.__LINE__, $content, $data],
            );

            return false;
        }

        if(empty($content))
        {
            return false;
        }

        return true;
    }
}