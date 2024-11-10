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

use DateTimeImmutable;

final  class YandexCommentsDTO
{

    /** Идентификатор комментария к отзыву. */
    private int $id;

    /** Идентификатор родительского комментария. */
    private ?int $parent;

    /** Информация об авторе отзыва. */
    private array $author;

    /** Может ли продавец изменять комментарий или удалять его. */
    private bool $canModify;

    /**
     * Статус комментария:
     * PUBLISHED — опубликован.
     * UNMODERATED — не проверен.
     * BANNED — заблокирован.
     * DELETED — удален.
     */
    private string $status;


    /**
     * Текст комментария.
     */
    private string $text;

    /** Дата и время создания отзыва.  */
    private DateTimeImmutable $created;


    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->parent = $data['parentId'] ?? null;
        $this->author = $data['author'];
        $this->canModify = $data['canModify'];
        $this->status = $data['status'];
        $this->text = $data['text'];
        $this->created = new DateTimeImmutable($data['createdAt']);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getParent(): ?int
    {
        return $this->parent;
    }

    public function getAuthor(): array
    {
        return $this->author;
    }

    public function isCanModify(): bool
    {
        return $this->canModify;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

}
