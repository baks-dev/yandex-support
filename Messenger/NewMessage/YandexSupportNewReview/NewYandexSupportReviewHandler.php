<?php

declare(strict_types=1);

namespace BaksDev\Yandex\Support\Messenger\NewMessage\YandexSupportNewReview;

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
use BaksDev\Yandex\Support\Api\Review\Get\GetComments\YandexGetCommentsRequest;
use BaksDev\Yandex\Support\Api\Review\Get\GetListReviews\YandexGetListReviewsRequest;
use BaksDev\Yandex\Support\Api\Review\Get\GetListReviews\YandexReviewDTO;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexReviewSupport;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class NewYandexSupportReviewHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private SupportHandler $supportHandler,
        private YandexGetListReviewsRequest $yandexGetListReviewsRequest,
        private YandexGetCommentsRequest $yandexGetCommentsRequest,
        private CurrentSupportEventByTicketInterface $currentSupportEventByTicket,
        private FindExistExternalMessageByIdInterface $findExistMessage,
        LoggerInterface $yandexSupportLogger,
    )
    {
        $this->logger = $yandexSupportLogger;
    }


    public function __invoke(NewYandexSupportReviewMessage $message): void
    {

        /** Получаем все отзывы */
        $reviews = $this->yandexGetListReviewsRequest
            ->profile($message->getProfile())

            // TODO: При первом запуске установить необходимое время или закомментировать
            // для получения всех отзывов
            ->dateFrom(new DateTimeImmutable('10 minutes'))
            ->findAll();

        if(!$reviews->valid())
        {
            return;
        }

        /** @var YandexReviewDTO $review */
        foreach($reviews as $review)
        {

            /** Получаем ID чата с отзывом  */
            $ticketId = $review->getReviewId();

            /** Получаем комментарии к чату */
            $comments = $this->yandexGetCommentsRequest
                ->profile($message->getProfile())
                ->feedback($ticketId)
                ->findAll();

            /** Если такой чат существует, сохраняем его event в переменную  */
            $supportEvent = $this->currentSupportEventByTicket
                ->forTicket($ticketId)
                ->find();


            $SupportDTO = new SupportDTO();

            if($supportEvent)
            {
                $supportEvent->getDto($SupportDTO);

            }

            if(false === $supportEvent)
            {
                /** Присваиваем приоритет сообщения "low" */
                $SupportDTO->setPriority(new SupportPriority(SupportPriorityLow::PARAM));

                /** SupportInvariableDTO */
                $SupportInvariableDTO = new SupportInvariableDTO();

                $SupportInvariableDTO->setProfile($message->getProfile());

                $SupportInvariableDTO
                    ->setType(new TypeProfileUid(TypeProfileYandexReviewSupport::TYPE)) // TypeProfileAvitoReviewSupport::TYPE
                    ->setTicket($review->getReviewId()) // Id тикета
                    ->setTitle($review->getTitle()); // Тема сообщения

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
                ->external($review->getReviewId())
                ->exist();

            if(false === $reviewExist)
            {
                /** @var SupportMessageDTO $SupportMessageDTO */
                $SupportMessageDTO = new SupportMessageDTO();

                $SupportMessageDTO
                    ->setName($review->getAuthor())         // Имя пользователя
                    ->setMessage($review->getText())        // Текст сообщения
                    ->setExternal($ticketId)                // Внешний (yandex) ID сообщения
                    ->setDate($review->getCreated())        // Дата сообщения
                    ->setInMessage();                       // Входящее сообщение

                /** Сохраняем данные в Support */
                $SupportDTO->addMessage($SupportMessageDTO);

            }

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

                    /** @var SupportMessageDTO $SupportMessageDTO */
                    $SupportMessageDTO = new SupportMessageDTO();

                    $SupportMessageDTO
                        ->setName($comment->getAuthor()['name'])  // Имя пользователя
                        ->setMessage($comment->getText())         // Текст сообщения
                        ->setExternal($comment->getId())          // Внешний (yandex) ID сообщения
                        ->setDate($review->getCreated());         // Дата сообщения


                    $comment->getAuthor()['type'] === 'BUSINESS' ?
                        $SupportMessageDTO->setOutMessage() :
                        $SupportMessageDTO->setInMessage();

                    /** Сохраняем данные в Support */
                    $SupportDTO->addMessage($SupportMessageDTO);
                }
            }

            /** Сохраняем в БД */
            $handle = $this->supportHandler->handle($SupportDTO);

            if(false === ($handle instanceof Support))
            {
                $this->logger->critical(
                    sprintf('yandex-support: Ошибка %s при обновлении чата', $handle),
                    [self::class.':'.__LINE__]
                );
            }
        }
    }
}
