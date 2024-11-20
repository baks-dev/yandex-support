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

namespace BaksDev\Yandex\Support\Messenger\ReplyMessage\YandexSupportReplyMessage;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventInterface;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Yandex\Support\Api\Messenger\Post\SendMessage\YandexSendMessageRequest;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexMessageSupport;
use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class ReplyYandexSupportMessageHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private YandexSendMessageRequest $messageRequest,
        private CurrentSupportEventInterface $currentSupportEvent,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        LoggerInterface $avitoSupportLogger,
    )
    {
        $this->logger = $avitoSupportLogger;
    }

    public function __invoke(SupportMessage $message): void
    {
        /** @var SupportEvent $support */
        $support = $message->getId();

        $supportEvent = $this
            ->currentSupportEvent
            ->forSupport($support)
            ->find();

        if(false === $supportEvent)
        {
            return;
        }

        /** @var SupportDTO $SupportDTO */
        $SupportDTO = new SupportDTO();
        $supportEvent->getDto($SupportDTO);

        /** @var SupportInvariableDTO $SupportInvariableDTO */
        $SupportInvariableDTO = $SupportDTO->getInvariable();

        /** Получаем тип профиля  */
        $TypeProfileUid = $SupportInvariableDTO->getType();

        /** Обрываем, если статус тикета "открытый" */
        if(false === $SupportDTO->getStatus()->equals(SupportStatusClose::class))
        {
            return;
        }

        /** Обрываем, если тип профиля не Авито Message */
        if(false === $TypeProfileUid->equals(TypeProfileYandexMessageSupport::TYPE))
        {
            return;
        }

        /**
         * Получаем последнее сообщение
         * @var SupportMessageDTO $supportMessage
         */
        $supportMessage = $SupportDTO->getMessages()->last();

        $Deduplicator = $this->deduplicator
            ->namespace('yandex-support')
            ->expiresAfter(DateInterval::createFromDateString('1 day'))
            ->deduplication([$supportMessage->getId(), self::class]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Если у сообщения есть внешний ID - сообщение пользовательское */
        if($supportMessage->getExternal() !== null)
        {
            return;
        }

        /** Отправка сообщения */
        $send = $this->messageRequest
            ->profile($SupportInvariableDTO->getProfile())
            ->yandexChat($SupportInvariableDTO->getTicket())
            ->message($supportMessage->getMessage())
            ->send();

        if(false === $send)
        {
            $this->logger->critical(
                sprintf(
                    'yandex-support: Пробуем отправить сообщение в тикет %s через 1 минуту',
                    $SupportInvariableDTO->getTicket()
                )
            );

            $this->messageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('1 minutes')],
                transport: 'yandex-support'
            );

            return;
        }

        $Deduplicator->save();

        $this->logger->info(sprintf('Отправили сообщение в тикет %s', $SupportInvariableDTO->getTicket()));

    }
}
