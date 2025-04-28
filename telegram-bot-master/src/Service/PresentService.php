<?php

namespace App\Service;

use App\Entity\Present;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class PresentService
{
    /** @var EntityManagerInterface */
    private $em;
    /** @var TgBotService */
    private $tgBotService;
    /** @var KeyboardService */
    private $keyboardService;

    /**
     * PresentService constructor.
     * @param EntityManagerInterface $em
     * @param TgBotService $tgBotService
     * @param KeyboardService $keyboardService
     */
    public function __construct(
        EntityManagerInterface $em,
        TgBotService $tgBotService,
        KeyboardService $keyboardService
    )
    {
        $this->em = $em;
        $this->tgBotService = $tgBotService;
        $this->keyboardService = $keyboardService;
    }

    /**
     * @param User $user
     * @param int $tokens
     */
    public function add(User $user, int $tokens): void
    {
        $present = (new Present())
            ->setUser($user)
            ->setTokens($tokens)
            ->setParsedAt(new \DateTimeImmutable())
            ->setNotified(false);
        $this->em->persist($present);
        if ($user->getTgId()) {
            $this->notify($present);
        }
    }

    /**
     * @param User $user
     */
    public function sendNotifications(User $user): void
    {
        if (!$user->getTgId()) {
            return;
        }
        foreach ($user->getPresents() as $present) {
            if (!$present->isNotified()) {
                $this->notify($present);
            }
        }
    }

    /**
     * @param Present $present
     */
    private function notify(Present $present): void
    {
        $user = $present->getUser();
        $lang = new LanguageService($user->getLanguageCode());
        try {
            $this->tgBotService->sendMessage(
                $user,
                $user->getTgId(),
                $lang->getLocalizedString('L_PRESENT_NOTIFICATION', [$present->getTokens()]),
                null,
                $this->keyboardService->getMainKeyboard($lang)
            );
            $user->setState(null);
            $present->setNotified(true)->setNotifiedAt(new \DateTimeImmutable());
        } catch(Exception $e) {}
    }
}