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

namespace BaksDev\Yandex\Support\Api\Messenger\Get\ListMessages;

use DateTimeImmutable;
use DateTimeZone;

final class YandexListMessagesDTO
{

    /** Идентификатор заказа. */
    private ?int $order;

    /** Идентификатор сообщения. */
    private int $externalId;

    /**
     * Отправитель.
     *
     * Кто отправил сообщение:
     *
     * PARTNER — магазин.
     * CUSTOMER — покупатель.
     * MARKET — Маркет.
     * SUPPORT — сотрудник службы поддержки Маркета.
     */
    private string $sender;

    /** Текст сообщения.*/
    private string $text;

    /** Дата и время создания чата. */
    private DateTimeImmutable $created;


    public function __construct(?int $orderId, array $data)
    {
        $this->order = $orderId;
        $this->externalId = $data['messageId'];
        $this->sender = $data['sender'];

        $moscowTimezone = new DateTimeZone(date_default_timezone_get());
        $this->created = (new DateTimeImmutable($data['createdAt']))->setTimezone($moscowTimezone);

        $this->text = $this->text($data['message'] ?? '', $data['payload'] ?? []);
    }

    /** Метод собирает контент сообщения */
    private function text(string $text, array $payloads = []): string
    {
        $result = [];

        if(!empty($payloads))
        {
            foreach($payloads as $payload)
            {
                $result [] = sprintf('<a href="%s" target="_blank" />', $payload['url']).$payload['name'].'<a/>';
            }
        }

        /** Если в сообщении находится ссылка без текста - присваиваем ссылку */
        $text = str_replace('/><a/>', 'class="ms-3" />Ссылка<a/>', $text);

        return !empty($result) ? $text.' '.implode(' ', $result) : trim($text);
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function getExternalId(): int
    {
        return $this->externalId;
    }

    public function getSender(): string
    {
        return $this->sender;
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
