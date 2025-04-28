<?php

namespace App\Service;

use App\Entity\User;

class KeyboardService
{
    /** @var UserChatService */
    private $userChatService;
    /** @var User */
    private $user;
    /** @var bool */
    private $isWebSearch;

    public function __construct(
        UserChatService $userChatService
    )
    {
        $this->userChatService = $userChatService;
    }

    public function getMainKeyboard(LanguageService $lang): array
    {
        $currentChatIndex = $this->user->getCurrentChatIndex();

        return [[
            $lang->getLocalizedString('L_MAIN_KEYBOARD_NEW_CHAT'),
            $lang->getLocalizedString('L_MAIN_KEYBOARD_WEB_SEARCH') . ($this->isWebSearch ? " ✅" : " ❌"),
            $lang->getLocalizedString('L_MAIN_KEYBOARD_NEW_IMAGE_GENERATION_CHAT'),
        ], array_merge([
            $lang->getLocalizedString('L_MAIN_KEYBOARD_TOOLZ'),
            $lang->getLocalizedString('L_MAIN_KEYBOARD_BUFFER'),
        ], $this->userChatService->getChatButtons($currentChatIndex))];
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function setIsWebSearch(bool $isWebSearch): void
    {
        $this->isWebSearch = $isWebSearch;
    }
}