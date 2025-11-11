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

namespace BaksDev\Yandex\Support\Api\Review\Get\GetListReviews;

use DateTimeImmutable;

final  class YandexReviewDTO
{

    /** Идентификатор отзыва. */
    private int $reviewId;

    /** Нужен ли ответ на отзыв. */
    private bool $needReaction;

    /**
     * Преобразуем массив 'identifiers', содержащий в себе
     * идентификаторы, которые связаны с отзывом. "orderId", "modelId"
     */
    private ?string $title;

    /** Имя автора отзыва. */
    private string $author;

    /**
     * description:
     * Текстовая часть отзыва.
     * "advantages"     - Описание плюсов товара в отзыве.  (string)
     * "comment"        - Комментарий в отзыве.             (string)
     * "disadvantages"  - Описание минусов товара в отзыве. (string)
     *
     * media:
     * Фото и видео.
     * "photos" - Ссылки на фото. (array)
     * "videos" - Ссылки на видео. (array)
     */
    private string $text;

    /** Дата и время создания отзыва.  */
    private DateTimeImmutable $created;

    private int $rating;


    public function __construct(array $data)
    {

        $this->reviewId = $data['feedbackId'];
        $this->needReaction = $data['needReaction'];

        /** Собираем тему сообщения в методе title()*/
        $this->title = $this->title($data);

        $this->author = !empty($data['author']) ?
            mb_ucfirst($data['author']) :
            'Анонимный пользователь';

        /** Собираем текст сообщения в методе text()*/
        $this->text = $this->text($data['description'], $data['media']);

        $this->created = new DateTimeImmutable($data['createdAt']);

        $this->rating = $data['statistics']['rating'];


    }

    /** Метод формирует тему сообщения */
    public function title(array $data): ?string
    {

        return !empty($data['identifiers']) ?
            sprintf(
                '<span class="badge text-bg-%s align-middle">',
                $this->color($data['statistics']['rating'])
            ).
            sprintf(
                '%s</span><span>&nbsp;Заказ: #%s</span> ', $data['statistics']['rating'],
                $data['identifiers']['orderId']
            )
            : null;

    }


    /** Метод формирует текст сообщения */
    private function text(array $description, array $media = []): string
    {
        $result = [];

        $text = sprintf(
            '%s %s %s',
            $description['comment'] ?? '',
            !empty($description['advantages']) ? '(+)'.$description['advantages'] : '',
            !empty($description['disadvantages']) ? '(-)'.$description['disadvantages'] : '',
        );

        if(!empty($media['photos']))
        {
            $result[] = '<br>';

            foreach($media['photos'] as $photo)
            {
                $result[] = sprintf(
                    '<img src="%s" width="300"/>', $photo
                );
            }

        }

        if(!empty($media['video']))
        {
            $result[] = '<br>';

            foreach($media['video'] as $video)
            {
                $result[] = sprintf(
                    '<a href="%s">Видео</a>',
                    $video
                );
            }

        }


        return !empty($result) ? $text.' '.implode(' ', $result) : trim($text);
    }

    private function color(int $rating): string
    {
        return match ($rating)
        {
            1, 2 => 'danger',
            3, 4 => 'warning',
            default => 'success'
        };
    }

    public function getReviewId(): int
    {
        return $this->reviewId;
    }

    public function isNeedReaction(): bool
    {
        return $this->needReaction;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getText(): string
    {
        return empty($this->text) ? 'Отзыв без комментария пользователя' : $this->text;
    }

    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    public function getRating(): int
    {
        return $this->rating;
    }
}
