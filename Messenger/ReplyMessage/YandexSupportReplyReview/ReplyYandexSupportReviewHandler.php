<?php

declare(strict_types=1);

namespace BaksDev\Yandex\Support\Messenger\ReplyMessage\YandexSupportReplyReview;


use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventInterface;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Yandex\Support\Api\Review\Post\ReplyToReview\YandexReplyToReviewRequest;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexReviewSupport;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class ReplyYandexSupportReviewHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private YandexReplyToReviewRequest $replyToReviewRequest,
        private CurrentSupportEventInterface $currentSupportEvent,
        LoggerInterface $yandexSupportLogger,
    )
    {
        $this->logger = $yandexSupportLogger;
    }

    public function __invoke(SupportMessage $message): void
    {

        /** @var SupportEvent $support */
        $support = $message->getId();

        $this->currentSupportEvent->forSupport($support);

        $supportEvent = $this->currentSupportEvent->find();

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

        /** Обрываем, если тип профиля не Авито Review */
        if(false === $TypeProfileUid->equals(TypeProfileYandexReviewSupport::TYPE))
        {
            return;
        }

        /** Если получено новое сообщение от клиента */
        $ticket = $SupportInvariableDTO->getTicket();

        /**
         * Получаем последнее сообщение
         *
         * @var SupportMessageDTO $message
         */
        $supportMessage = $SupportDTO->getMessages()->last();

        /** Если у сообщения есть внешний ID, то обрываем  */
        if($supportMessage->getExternal() !== null)
        {
            $this->logger->critical(
                'yandex-support: Отсутствует сообщение для отправки'.self::class.':'.__LINE__,
                $SupportDTO->getMessages()->toArray()
            );

            return;
        }

        /** Отправляем ответ в чат с отзывами */
        $this->replyToReviewRequest
            ->profile($SupportInvariableDTO->getProfile())
            ->feedback($ticket)
            ->message($supportMessage->getMessage())
            ->send();
    }
}
