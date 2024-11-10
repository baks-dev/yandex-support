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

namespace BaksDev\Yandex\Support\Commands\Upgrade;

use BaksDev\Users\Profile\TypeProfile\Entity\TypeProfile;
use BaksDev\Users\Profile\TypeProfile\Repository\ExistTypeProfile\ExistTypeProfileInterface;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use BaksDev\Users\Profile\TypeProfile\UseCase\Admin\NewEdit\Trans\TransDTO;
use BaksDev\Users\Profile\TypeProfile\UseCase\Admin\NewEdit\TypeProfileDTO;
use BaksDev\Users\Profile\TypeProfile\UseCase\Admin\NewEdit\TypeProfileHandler;
use BaksDev\Yandex\Support\Types\ProfileType\TypeProfileYandexMessageSupport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'baks:users-profile-type:yandex-support-message',
    description: 'Добавляет тип профилей пользователя техподдержки Сообщений'
)]
class UpgradeProfileTypeYandexMessageSupportCommand extends Command
{
    public function __construct(
        private readonly ExistTypeProfileInterface $existTypeProfile,
        private readonly TranslatorInterface $translator,
        private readonly TypeProfileHandler $profileHandler,
    )
    {
        parent::__construct();
    }

    /** Добавляет тип профиля Yandex Message */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $TypeProfileUid = new TypeProfileUid(TypeProfileYandexMessageSupport::class);

        /** Проверяем наличие типа Yandex Support Message */
        $exists = $this->existTypeProfile->isExistTypeProfile($TypeProfileUid);

        if(!$exists)
        {
            $io = new SymfonyStyle($input, $output);
            $io->text('Добавляем тип профиля Yandex Support Message');

            $TypeProfileDTO = new TypeProfileDTO();
            $TypeProfileDTO->setSort(TypeProfileYandexMessageSupport::priority());
            $TypeProfileDTO->setProfile($TypeProfileUid);

            $TypeProfileTranslateDTO = $TypeProfileDTO->getTranslate();

            /**
             * Присваиваем настройки локали типа профиля
             *
             * @var TransDTO $ProfileTrans
             */
            foreach($TypeProfileTranslateDTO as $ProfileTrans)
            {
                $name = $this->translator->trans('yandex-support.message.name', domain: 'profile.type', locale: $ProfileTrans->getLocal()->getLocalValue());
                $desc = $this->translator->trans('yandex-support.message.desc', domain: 'profile.type', locale: $ProfileTrans->getLocal()->getLocalValue());

                $ProfileTrans->setName($name);
                $ProfileTrans->setDescription($desc);
            }

            $handle = $this->profileHandler->handle($TypeProfileDTO);

            if(!$handle instanceof TypeProfile)
            {
                $io->error(sprintf('Ошибка %s при добавлении типа профиля', $handle));
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /** Чам выше число - тем первым в итерации будет значение */
    public static function priority(): int
    {
        return 424;
    }

}
