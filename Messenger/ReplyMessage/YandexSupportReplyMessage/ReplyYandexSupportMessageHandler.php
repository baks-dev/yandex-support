<?php

declare(strict_types=1);

namespace BaksDev\Yandex\Support\Messenger\ReplyMessage\YandexSupportReplyMessage;


use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventInterface;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Yandex\Support\Api\Messenger\Post\SendMessage\YandexSendMessageRequest;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexMessageSupport;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class ReplyYandexSupportMessageHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private YandexSendMessageRequest $messageRequest,
        private CurrentSupportEventInterface $currentSupportEvent,
        LoggerInterface $avitoSupportLogger,
    )
    {
        $this->logger = $avitoSupportLogger;
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

        /** Обрываем, если тип профиля не Авито Message */
        if(false === $TypeProfileUid->equals(TypeProfileYandexMessageSupport::TYPE))
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

        /** Отправка сообщения */
        $this->messageRequest
            ->profile($SupportInvariableDTO->getProfile())
            ->yandexChat($ticket)
            ->message($supportMessage->getMessage())
            ->send();
    }
}
