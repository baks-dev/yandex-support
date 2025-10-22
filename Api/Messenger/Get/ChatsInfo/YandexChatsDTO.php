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

namespace BaksDev\Yandex\Support\Api\Messenger\Get\ChatsInfo;

use DateTimeImmutable;

final readonly class YandexChatsDTO
{
    /** Идентификатор чата. */
    private int $id;

    /** Идентификатор заказа. */
    private ?int $order;

    /**
     * Статус чата:
     *
     * NEW — новый чат.
     * WAITING_FOR_CUSTOMER — нужен ответ покупателя.
     * WAITING_FOR_PARTNER — нужен ответ магазина.
     * WAITING_FOR_ARBITER — нужен ответ арбитра.
     * WAITING_FOR_MARKET — нужен ответ Маркета.
     * FINISHED — чат завершен.
     */
    private string $status;

    /**
     * Тип чата:
     *
     * CHAT — чат с покупателем.
     * ARBITRAGE — спор.
     */
    private string $type;


    /** Дата и время создания чата. */
    private DateTimeImmutable $created;

    /** Дата и время последнего сообщения в чате. */
    private DateTimeImmutable $updated;


    public function __construct(array $data)
    {
        $this->id = $data['chatId'];
        $this->order = $data['orderId'] ?? null;
        $this->status = $data['status'];
        $this->type = $data['type'];
        $this->created = (new DateTimeImmutable($data['createdAt']));
        $this->updated = (new DateTimeImmutable($data['updatedAt']));
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    public function getUpdated(): DateTimeImmutable
    {
        return $this->updated;
    }
}
