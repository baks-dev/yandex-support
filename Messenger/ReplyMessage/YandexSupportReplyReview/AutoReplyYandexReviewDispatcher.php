<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Yandex\Support\Messenger\ReplyMessage\YandexSupportReplyReview;

use BaksDev\Ozon\Support\Type\OzonReviewProfileType;
use BaksDev\Support\Answer\Service\AutoMessagesReply;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexReviewSupport;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * preview @see NewOzonReviewInfoDispatcher
 * next @see ReplyOzonReviewDispatcher
 */
#[Autoconfigure(public: true)]
#[AsMessageHandler(priority: 0)]
final readonly class AutoReplyYandexReviewDispatcher
{
    public function __construct(
        #[Target('yandexSupportLogger')] private LoggerInterface $logger,
        private SupportHandler $SupportHandler,
        private CurrentSupportEventRepository $CurrentSupportEventRepository,
    ) {}

    /**
     * - добавляет сообщение с автоматическим ответом
     * - закрывает чат
     * - сохраняет новое состояние чата в БД
     */
    public function __invoke(AutoReplyYandexReviewMessage $message): void
    {
        $CurrentSupportEvent = $this->CurrentSupportEventRepository
            ->forSupport($message->getId())
            ->find();

        if(false === ($CurrentSupportEvent instanceof SupportEvent))
        {
            $this->logger->critical(
                'ozon-support: Ошибка получения события по идентификатору :'.$message->getId(),
                [self::class.':'.__LINE__],
            );

            return;
        }


        /** @var SupportDTO $SupportDTO */
        $SupportDTO = $CurrentSupportEvent->getDto(SupportDTO::class);

        // обрабатываем только на открытый тикет
        if(false === ($SupportDTO->getStatus()->getSupportStatus() instanceof SupportStatusOpen))
        {
            return;
        }

        $supportInvariableDTO = $SupportDTO->getInvariable();

        if(false === ($supportInvariableDTO instanceof SupportInvariableDTO))
        {
            return;
        }

        // проверяем тип профиля у чата
        $TypeProfileUid = $supportInvariableDTO->getType();

        if(false === $TypeProfileUid->equals(TypeProfileYandexReviewSupport::TYPE))
        {
            return;
        }

        // формируем сообщение в зависимости от условий отзыва
        $reviewRating = $message->getRating();

        /**
         * Текст сообщения в зависимости от рейтинга
         * по умолчанию текс с высоким рейтингом, 5 «HIGH»
         */

        $AutoMessagesReply = new AutoMessagesReply();
        $answerMessage = $AutoMessagesReply->high();

        if($reviewRating === 4 || $reviewRating === 3)
        {
            $answerMessage = $AutoMessagesReply->avg();
        }

        if($reviewRating < 3)
        {
            $answerMessage = $AutoMessagesReply->low();
        }

        /** Отправляем сообщение клиенту */

        $supportMessageDTO = new SupportMessageDTO()
            ->setName('auto (Bot Seller)')
            ->setMessage($answerMessage)
            ->setDate(new DateTimeImmutable('now'))
            ->setOutMessage();

        $SupportDTO
            ->setStatus(new SupportStatus(SupportStatusClose::PARAM)) // закрываем чат
            ->addMessage($supportMessageDTO) // добавляем сформированное сообщение
        ;

        // сохраняем ответ
        $Support = $this->SupportHandler->handle($SupportDTO);

        if(false === ($Support instanceof Support))
        {
            $this->logger->critical(
                'ozon-support: Ошибка при отправке автоматического ответа на Яндекс отзыв',
                [$Support, self::class.':'.__LINE__],
            );
        }
    }
}
