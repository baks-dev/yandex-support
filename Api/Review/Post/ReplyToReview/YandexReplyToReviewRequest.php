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

namespace BaksDev\Yandex\Support\Api\Review\Post\ReplyToReview;

use BaksDev\Yandex\Market\Api\YandexMarket;


final class YandexReplyToReviewRequest extends YandexMarket
{
    /** Идентификатор отзыва. */
    private int $feedback;

    /** Текст сообщения. */
    private string $message;

    /**
     * Метод принимает и устанавливает идентификатор
     * отзыва, на который нужно ответить.
     */
    public function feedback(int|string $feedbackId): self
    {
        $this->feedback = (int) $feedbackId;
        return $this;
    }

    /**
     * Метод принимает и устанавливает текст ответа сообщения.
     */
    public function message(string $text): self
    {
        $this->message = $text;
        return $this;
    }

    /** Возвращает массив с body */
    private function body(): array
    {
        return [
            "feedbackId" => $this->feedback,
            "comment" => [
                //                "id" => 0,        // Идентификатор комментария, который нужно изменить.
                //                "parentId" => 0, // Идентификатор родительского комментария, на который нужно ответить.
                "text" => $this->message,
            ],
        ];
    }


    /**
     * Добавляет новый комментарий магазина или изменяет комментарий, который магазин оставлял ранее.
     *
     * @see https://yandex.ru/dev/market/partner-api/doc/ru/reference/goods-feedback/updateGoodsFeedbackComment
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
                sprintf('/businesses/%s/goods-feedback/comments/update', $this->getBusiness()),
                [
                    "json" => $this->body(),
                ],
            );


        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('yandex-support: ошибка %s отправке сообщения на отзыв', $response->getStatusCode()),
                [$this->body(), $content, self::class.':'.__LINE__],
            );

            /** Отзывы не найдены */
            if(current($content['errors'])['code'] === 'NOT_FOUND')
            {
                return true;
            }

            return false;
        }

        if(empty($content) && $content['result']['status'] !== 'PUBLISHED')
        {
            $this->logger->critical('yandex-support: сообщение не удалось опубликовать', [self::class.':'.__LINE__]);

            return false;
        }

        return true;
    }
}
