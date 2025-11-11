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

namespace BaksDev\Yandex\Support\Messenger\Schedules\YandexSupportNewReview;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
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
use BaksDev\Yandex\Support\Api\Review\Get\GetComments\YandexGetCommentsRequest;
use BaksDev\Yandex\Support\Api\Review\Get\GetListReviews\YandexGetListReviewsRequest;
use BaksDev\Yandex\Support\Messenger\ReplyMessage\YandexSupportReplyReview\AutoReplyYandexReviewMessage;
use BaksDev\Yandex\Support\Schedule\YandexGetNewReview\YandexGetNewReviewSchedule;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexReviewSupport;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class NewYandexSupportReviewHandler
{
    public function __construct(
        #[Target('yandexSupportLogger')] private LoggerInterface $logger,
        private SupportHandler $supportHandler,
        private YandexGetListReviewsRequest $yandexGetListReviewsRequest,
        private YandexGetCommentsRequest $yandexGetCommentsRequest,
        private CurrentSupportEventByTicketInterface $currentSupportEventByTicket,
        private FindExistExternalMessageByIdInterface $findExistMessage,
        private DeduplicatorInterface $deduplicator,
        private YaMarketTokensByProfileInterface $YaMarketTokensByProfile,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(NewYandexSupportReviewMessage $message): void
    {
        /** Дедубликатор от повторных вызовов */

        $isExecuted = $this
            ->deduplicator
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

        $YaMarketTokenUid = $tokensByProfile->current();

        /**
         * Получаем все непрочитанные отзывы
         */

        $from = new DateTimeImmutable()
            ->setTimezone(new DateTimeZone('GMT'))

            // периодичность scheduler
            ->sub(DateInterval::createFromDateString(YandexGetNewReviewSchedule::INTERVAL))

            // запас на runtime
            ->sub(DateInterval::createFromDateString('1 hour'));

        $reviews = $this->yandexGetListReviewsRequest
            ->forTokenIdentifier($YaMarketTokenUid)
            ->dateFrom($from)
            ->findAll();

        if(false === $reviews->valid())
        {
            return;
        }

        foreach($reviews as $YandexReviewDTO)
        {
            if(empty($YandexReviewDTO->getText()))
            {
                continue;
            }

            /** Получаем ID чата с отзывом  */
            $ticketId = $YandexReviewDTO->getReviewId();

            /** Получаем комментарии к чату */
            $comments = $this->yandexGetCommentsRequest
                ->forTokenIdentifier($YaMarketTokenUid)
                ->feedback($ticketId)
                ->findAll();

            /** Если такой чат существует, сохраняем его event в переменную  */
            $supportEvent = $this->currentSupportEventByTicket
                ->forTicket($ticketId)
                ->find();


            $SupportDTO = $supportEvent instanceof SupportEvent
                ? $supportEvent->getDto(SupportDTO::class)
                : new SupportDTO(); // done


            if(false === $supportEvent)
            {
                /** Присваиваем приоритет сообщения "low" */
                $SupportDTO
                    ->setPriority(new SupportPriority(SupportPriorityLow::PARAM))
                    ->getToken()->setValue($YaMarketTokenUid);

                /** SupportInvariableDTO */
                $SupportInvariableDTO = new SupportInvariableDTO();

                $SupportInvariableDTO
                    //->setProfile($message->getProfile()) // Профиль
                    ->setType(new TypeProfileUid(TypeProfileYandexReviewSupport::TYPE)) // TypeProfileAvitoReviewSupport::TYPE
                    ->setTicket($YandexReviewDTO->getReviewId()) // Id тикета
                    ->setTitle($YandexReviewDTO->getTitle()); // Тема сообщения

                /** Сохраняем данные SupportInvariableDTO в Support */
                $SupportDTO->setInvariable($SupportInvariableDTO);
            }

            /** Присваиваем статус "Открытый", так как сообщение еще не прочитано   */
            $SupportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));

            if(!$reviews->valid())
            {
                return;
            }

            /** Если такое сообщение уже есть в БД, то пропускаем */
            $reviewExist = $this->findExistMessage
                ->external($YandexReviewDTO->getReviewId())
                ->exist();

            if(false === $reviewExist)
            {
                $SupportMessageDTO = new SupportMessageDTO();

                $SupportMessageDTO
                    ->setName($YandexReviewDTO->getAuthor())         // Имя пользователя
                    ->setMessage($YandexReviewDTO->getText())        // Текст сообщения
                    ->setDate($YandexReviewDTO->getCreated())        // Дата сообщения
                    ->setExternal($ticketId)                         // Внешний (yandex) ID сообщения
                    ->setInMessage();                                // Входящее сообщение

                /** Сохраняем данные в Support */
                $SupportDTO->addMessage($SupportMessageDTO);

            }

            $comment = null;

            if($comments->valid())
            {
                foreach($comments as $comment)
                {
                    /** Если такое сообщение уже есть в БД, то пропускаем */
                    $commentExist = $this->findExistMessage
                        ->external($comment->getId())
                        ->exist();

                    if($commentExist)
                    {
                        continue;
                    }

                    if(empty($comment->getText()))
                    {
                        continue;
                    }

                    /** @var SupportMessageDTO $SupportMessageDTO */
                    $SupportMessageDTO = new SupportMessageDTO();

                    $SupportMessageDTO
                        ->setName($comment->getAuthor()['name'])  // Имя пользователя
                        ->setMessage($comment->getText())         // Текст сообщения
                        ->setExternal($comment->getId())          // Внешний (yandex) ID сообщения
                        ->setDate($YandexReviewDTO->getCreated());         // Дата сообщения


                    $comment->getAuthor()['type'] === 'BUSINESS' ?
                        $SupportMessageDTO->setOutMessage() :
                        $SupportMessageDTO->setInMessage();

                    /** Сохраняем данные в Support */
                    $SupportDTO->addMessage($SupportMessageDTO);
                }
            }

            /** Сохраняем в БД */
            $Support = $this->supportHandler->handle($SupportDTO);

            if(false === ($Support instanceof Support))
            {
                $this->logger->critical(
                    sprintf('yandex-support: Ошибка %s при обновлении чата', $Support),
                    [self::class.':'.__LINE__],
                );

                return;
            }


            // после добавления отзыва в БД - инициирую авто ответ по условию

            /**
             * Условия ответа на отзывы
             *
             * рейтинг равен 5 с текстом:
             * - авто комментарий с благодарностью (сообщение)
             *
             * рейтинг меньше 5 и без текста:
             * - авто комментарий с извинениями (сообщение)
             *
             * рейтинг меньше 5 с текстом:
             * - отвечает контент менеджер
             */

            if($YandexReviewDTO->getRating() === 5 || empty($comment?->getText()))
            {
                $this->messageDispatch->dispatch(
                    message: new AutoReplyYandexReviewMessage($Support->getId(), $YandexReviewDTO->getRating()),
                    transport: 'yandex-support',
                );
            }
        }
    }
}
