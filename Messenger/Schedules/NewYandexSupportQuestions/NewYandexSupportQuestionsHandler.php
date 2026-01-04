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

namespace BaksDev\Yandex\Support\Messenger\Schedules\NewYandexSupportQuestions;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\CurrentProductByBarcodeResult;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\ProductConstByArticleInterface;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByEventInterface;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByEventResult;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Repository\ExistTicket\ExistSupportTicketInterface;
use BaksDev\Support\Type\Priority\SupportPriority;
use BaksDev\Support\Type\Priority\SupportPriority\Collection\SupportPriorityLow;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use BaksDev\Yandex\Market\Repository\YaMarketTokensByProfile\YaMarketTokensByProfileInterface;
use BaksDev\Yandex\Support\Api\Questions\Get\YandexGetQuestionsRequest;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexQuestionSupport;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler(priority: 0)]
final readonly class NewYandexSupportQuestionsHandler
{
    public function __construct(
        #[Target('yandexSupportLogger')] private LoggerInterface $logger,
        private DeduplicatorInterface $deduplicator,
        private YaMarketTokensByProfileInterface $YaMarketTokensByProfile,
        private YandexGetQuestionsRequest $YandexGetQuestionsRequest,
        private ExistSupportTicketInterface $ExistSupportTicketRepository,
        private ProductConstByArticleInterface $ProductConstByArticleRepository,
        private ProductDetailByEventInterface $ProductDetailByEventRepository,
        private Environment $environment
    ) {}

    public function __invoke(NewYandexSupportQuestionsMessage $message): void
    {

        /** Дедубликатор от повторных вызовов */

        $isExecuted = $this
            ->deduplicator
            ->namespace('yandex-support')
            ->expiresAfter('5 minutes')
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

        $call = $this->environment->getExtension(CallTwigFuncExtension::class);

        foreach($tokensByProfile as $YaMarketTokenUid)
        {
            /**
             * Получаем вопросы в запросе
             */

            $questions = $this->YandexGetQuestionsRequest
                ->forTokenIdentifier($YaMarketTokenUid)
                ->findAll();

            if(false === $questions || false === $questions->valid())
            {
                continue;
            }

            foreach($questions as $YandexQuestionDTO)
            {

                /** Получаем ID вопроса */
                $ticketId = $YandexQuestionDTO->getId();

                $DeduplicatorTicket = $this->deduplicator
                    ->namespace('yandex-support')
                    ->expiresAfter('1 day')
                    ->deduplication([$ticketId, self::class]);

                if($DeduplicatorTicket->isExecuted())
                {
                    continue;
                }

                /** Пропускаем, если такой тикет существует */

                $isExist = $this->ExistSupportTicketRepository
                    ->ticket($ticketId)
                    ->exist();

                if(true === $isExist)
                {
                    $DeduplicatorTicket->save();
                    continue;
                }

                $SupportDTO = new SupportDTO();

                /** Присваиваем статус "Открытый", так как сообщение еще не прочитано   */
                $SupportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));


                /**
                 * Получаем продукцию по артикулу
                 */

                $CurrentProductByBarcodeResult = $this->ProductConstByArticleRepository->find($YandexQuestionDTO->getArticle());

                if(false === ($CurrentProductByBarcodeResult instanceof CurrentProductByBarcodeResult))
                {
                    $this->logger->critical(
                        sprintf(
                            'yandex-support: Ошибка при получении информации о продукте с артикулом %s',
                            $YandexQuestionDTO->getArticle(),
                        ),
                        [self::class.':'.__LINE__],
                    );

                    continue;
                }


                $ProductDetailByEventResult = $this->ProductDetailByEventRepository
                    ->event($CurrentProductByBarcodeResult->getEvent())
                    ->offer($CurrentProductByBarcodeResult->getOffer())
                    ->variation($CurrentProductByBarcodeResult->getVariation())
                    ->modification($CurrentProductByBarcodeResult->getModification())
                    ->findResult();


                if(false === ($ProductDetailByEventResult instanceof ProductDetailByEventResult))
                {
                    $this->logger->critical(
                        sprintf(
                            'yandex-support: Ошибка при получении детальной информации о продукте с артикулом %s',
                            $YandexQuestionDTO->getArticle(),
                        ),
                        [self::class.':'.__LINE__],
                    );
                }

                /** Формируем название продукта */

                $name = $ProductDetailByEventResult->getProductName();

                /**
                 * Множественный вариант
                 */

                $variation = $call->call(
                    $this->environment,
                    $ProductDetailByEventResult->getProductVariationValue(),
                    $ProductDetailByEventResult->getProductVariationReference().'_render',
                );

                $name .= $variation ? ' '.trim($variation) : '';


                /**
                 * Модификация множественного варианта
                 */

                $modification = $call->call(
                    $this->environment,
                    $ProductDetailByEventResult->getProductModificationValue(),
                    $ProductDetailByEventResult->getProductModificationReference().'_render',
                );

                $name .= $modification ? ' '.trim($modification) : '';


                /**
                 * Торговое предложение
                 */

                $offer = $call->call(
                    $this->environment,
                    $ProductDetailByEventResult->getProductOfferValue(),
                    $ProductDetailByEventResult->getProductOfferReference().'_render',
                );


                $name .= $offer ? ' '.trim($offer) : '';


                $name .= $ProductDetailByEventResult->getProductOfferPostfix() ? ' '.$ProductDetailByEventResult->getProductOfferPostfix() : '';
                $name .= $ProductDetailByEventResult->getProductVariationPostfix() ? ' '.$ProductDetailByEventResult->getProductVariationPostfix() : '';
                $name .= $ProductDetailByEventResult->getProductModificationPostfix() ? ' '.$ProductDetailByEventResult->getProductModificationPostfix() : '';


                $SupportDTO
                    ->setPriority(new SupportPriority(SupportPriorityLow::PARAM))
                    ->getToken()->setValue($YaMarketTokenUid);

                $SupportInvariableDTO = new SupportInvariableDTO()
                    ->setType(new TypeProfileUid(TypeProfileYandexQuestionSupport::TYPE))
                    ->setTicket($ticketId) //  Id тикета
                    ->setTitle($name);  // Тема сообщения


                $SupportMessageDTO = new SupportMessageDTO();

            }

        }

    }
}
