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

namespace BaksDev\Yandex\Support\Messenger\Schedules\YandexSupportNewMessage;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderNumber\CurrentOrderEventByNumberInterface;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Repository\FindExistMessage\FindExistExternalMessageByIdInterface;
use BaksDev\Support\Repository\SupportCurrentEventByTicket\CurrentSupportEventByTicketInterface;
use BaksDev\Support\Type\Priority\SupportPriority;
use BaksDev\Support\Type\Priority\SupportPriority\Collection\SupportPriorityLow;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use BaksDev\Yandex\Market\Repository\YaMarketTokensByProfile\YaMarketTokensByProfileInterface;
use BaksDev\Yandex\Support\Api\Messenger\Get\ChatsInfo\YandexChatsDTO;
use BaksDev\Yandex\Support\Api\Messenger\Get\ChatsInfo\YandexGetChatsInfoRequest;
use BaksDev\Yandex\Support\Api\Messenger\Get\ListMessages\YandexGetListMessagesRequest;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexMessageSupport;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class NewYandexSupportHandler
{
    public function __construct(
        #[Target('yandexSupportLogger')] private LoggerInterface $logger,
        private SupportHandler $supportHandler,
        private CurrentSupportEventByTicketInterface $currentSupportEventByTicket,
        private FindExistExternalMessageByIdInterface $findExistMessage,
        private YandexGetChatsInfoRequest $getChatsInfoRequest,
        private YandexGetListMessagesRequest $messagesRequest,
        private DeduplicatorInterface $deduplicator,
        private YaMarketTokensByProfileInterface $YaMarketTokensByProfile,
        private CurrentOrderEventByNumberInterface $CurrentOrderEventByNumberRepository
    ) {}


    public function __invoke(NewYandexSupportMessage $message): void
    {
        /** Дедубликатор от повторных вызовов */

        $isExecuted = $this
            ->deduplicator
            ->namespace('yandex-support')
            ->expiresAfter('1 minute')
            ->deduplication([$message->getProfile(), self::class]);

        if($isExecuted->isExecuted())
        {
            return;
        }

        $isExecuted->save();


        /** Получаем все токены профиля */

        $tokensByProfile = $this->YaMarketTokensByProfile
            ->findAll($message->getProfile());

        if(false === $tokensByProfile || false === $tokensByProfile->valid())
        {
            return;
        }


        foreach($tokensByProfile as $YaMarketTokenUid)
        {
            /**
             * Получаем все непрочитанные чаты
             */

            $chats = $this->getChatsInfoRequest
                ->forTokenIdentifier($YaMarketTokenUid)
                ->findAll();

            if(false === $chats->valid())
            {
                continue;
            }

            /** @var YandexChatsDTO $chat */
            foreach($chats as $chat)
            {
                /** Получаем ID чата */
                $ticketId = $chat->getId();

                $DeduplicatorTicket = $this->deduplicator
                    ->namespace('yandex-support')
                    ->expiresAfter('30 seconds')
                    ->deduplication([$ticketId, self::class]);

                if($DeduplicatorTicket->isExecuted())
                {
                    continue;
                }

                /** Если такой тикет уже существует в БД, то присваиваем в переменную $supportEvent */
                $supportEvent = $this->currentSupportEventByTicket
                    ->forTicket($ticketId)
                    ->find();

                /** Получаем сообщения чата  */
                $listMessages = $this->messagesRequest
                    ->forTokenIdentifier($YaMarketTokenUid)
                    ->chat($ticketId)
                    ->findAll();

                if(false === $listMessages->valid())
                {
                    continue;
                }

                $SupportDTO = true === ($supportEvent instanceof SupportEvent)
                    ? $supportEvent->getDto(SupportDTO::class)
                    : new SupportDTO(); // done

                /** Присваиваем значения по умолчанию для нового тикета */
                if(false === ($supportEvent instanceof SupportEvent))
                {
                    /** Присваиваем токен для последующего поиска */
                    $SupportDTO->getToken()->setValue($YaMarketTokenUid);

                    /** Присваиваем приоритет сообщения "высокий", так как это сообщение от пользователя */
                    $SupportDTO->setPriority(new SupportPriority(SupportPriorityLow::PARAM));


                    $SupportInvariableDTO = new SupportInvariableDTO()
                        ->setType(new TypeProfileUid(TypeProfileYandexMessageSupport::TYPE)) // TypeProfileYandexMessageSupport::TYPE
                        ->setTicket($ticketId)                                       //  Id тикета
                        ->setTitle(is_null($chat->getOrder()) ? 'Без темы' : sprintf('Заказ #%s', $chat->getOrder()));  // Тема сообщения

                    /**
                     * Получаем профиль пользователя по идентификатору заказа и присваиваем региону
                     */

                    if(false === is_null($chat->getOrder()))
                    {
                        $OrderEvent = $this->CurrentOrderEventByNumberRepository->find('Y-'.$chat->getOrder());

                        if($OrderEvent instanceof OrderEvent)
                        {
                            $SupportInvariableDTO->setProfile($OrderEvent->getOrderProfile()); // Профиль по заказу
                        }
                    }

                    /** Сохраняем данные SupportInvariableDTO в Support */
                    $SupportDTO->setInvariable($SupportInvariableDTO);
                }

                /** Присваиваем статус "Открытый", так как сообщение еще не прочитано   */
                $SupportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));

                $isHandle = false;

                foreach($listMessages as $listMessage)
                {
                    /** Пропускаем, если сообщение системное  */
                    if($listMessage->getSender() === 'MARKET')
                    {
                        continue;
                    }

                    $DeduplicatorMessage = $this->deduplicator
                        ->namespace('yandex-support')
                        ->expiresAfter('1 day')
                        ->deduplication([$listMessage->getExternalId(), self::class]);

                    if($DeduplicatorMessage->isExecuted())
                    {
                        continue;
                    }

                    /** Если такое сообщение уже есть в БД, то пропускаем */
                    $messageExist = $this->findExistMessage
                        ->external($listMessage->getExternalId())
                        ->exist();

                    if($messageExist)
                    {
                        continue;
                    }

                    /**
                     * SupportMessageDTO
                     */

                    $SupportMessageDTO = new SupportMessageDTO();

                    $SupportMessageDTO
                        ->setExternal($listMessage->getExternalId())    // Внешний id сообщения
                        ->setName($listMessage->getSender())            // Имя отправителя сообщения
                        ->setMessage($listMessage->getText())           // Текст сообщения
                        ->setDate($listMessage->getCreated())           // Дата сообщения
                    ;

                    /** Если это сообщение изначально наше, то сохраняем как 'out' */
                    $listMessage->getSender() === 'PARTNER' ?
                        $SupportMessageDTO->setOutMessage() :
                        $SupportMessageDTO->setInMessage();

                    /** Сохраняем данные SupportMessageDTO в Support */
                    $isAddMessage = $SupportDTO->addMessage($SupportMessageDTO);

                    if(false === $isHandle && true === $isAddMessage)
                    {
                        $isHandle = true;
                    }

                    $DeduplicatorMessage->save();
                }

                if($isHandle)
                {
                    /** Сохраняем в БД */
                    $handle = $this->supportHandler->handle($SupportDTO);

                    if(false === ($handle instanceof Support))
                    {
                        $this->logger->critical(
                            sprintf('yandex-support: Ошибка %s при обновлении чата', $handle),
                            [self::class.':'.__LINE__],
                        );
                    }
                }

                $DeduplicatorTicket->save();
            }
        }

        $isExecuted->delete();
    }
}
