<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserChat;
use App\Repository\UserChatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UserChatService
{
    /** @var UserChatRepository */
    private $userChatRepo;
    /** @var EntityManagerInterface */
    private $em;
    /** @var string */
    private $botLang;
    /** @var int[] */
    private const CHAT_BUTTONS = ['1️⃣' => 1, '2️⃣' => 2, '3️⃣' => 3, '4️⃣' => 4, '📝' => 5];

    /**
     * UserChatService constructor.
     * @param UserChatRepository $userChatRepo
     * @param EntityManagerInterface $em
     * @param ParameterBagInterface $params
     */
    public function __construct(
        UserChatRepository $userChatRepo,
        EntityManagerInterface $em,
        ParameterBagInterface $params
    )
    {
        $this->userChatRepo = $userChatRepo;
        $this->em = $em;
        $telegramSettings = $params->get('telegramSettings');
        $this->botLang = $telegramSettings['botLanguage'];
    }

    /**
     * @param int $userId
     * @param int $page
     * @param int $itemsPerPage
     * @return UserChat[]
     */
    public function getPaginationChats(int $userId, int $page, int $itemsPerPage): array
    {
        if ($page === 1) {
            return $this->getDefaultChats();
        }

        return $this->findAllOrderedByChatIndex($userId, $page, $itemsPerPage);
    }

    public function getTotalPages(int $userId, int $itemsPerPage): int
    {
        return $this->userChatRepo->getTotalPages($userId, $itemsPerPage);
    }

    /**
     * @return UserChat[]
     */
    public function getDefaultChats(): array
    {
        $defaultChats = [];
        foreach ($this::CHAT_BUTTONS as $key => $value) {
            $defaultChats[] = (new UserChat())
                ->setChatIndex($value)
                ->setName($key);
        }
        return $defaultChats;
    }

    /**
     * @return UserChat[]
     */
    public function findAllOrderedByChatIndex(int $userId, int $page, int $itemsPerPage): array
    {
        $userChats = $this->userChatRepo->findPaginatedExcludingFirstFive($userId, $page, $itemsPerPage);
        return array_filter(
            $userChats,
            function (UserChat $chat) {
                return !in_array($chat->getChatIndex(), array_values($this::CHAT_BUTTONS), true);
            }
        );
    }

    /**
     * @param User $user
     * @param int|null $newChatIndex
     * @param string|null $name
     * @return UserChat
     */
    public function getOrAddUserChat(User $user, int $newChatIndex = null, ?string $name = null): UserChat
    {
        $lang = new LanguageService($this->botLang);
        $chatIndex = $newChatIndex ?? $user->getCurrentChatIndex();

        if (is_null($chatIndex)) {
            $chatIndex = 1;
            $user->setCurrentChatIndex($chatIndex);
        }
        $userChat = $this->userChatRepo->findOneByUserIdAndChatIndex($user->getId(), $chatIndex);
        if (!$userChat) {
            $userChat = (new UserChat())
                ->setUser($user)
                ->setChatIndex($chatIndex)
                ->setName($name)
                ->setContextRemember(true)
                ->setLinksParse(true)
                ->setFormulaToImage(true)
                ->setAnswerToVoice(false)
                ->resetContextCounter()
                ->resetSystemPrompt()
            ;
            if ($chatIndex === 5) {
                $userChat->setContextRemember(false)->setSystemPrompt(
                    $lang->getLocalizedString('L_FIFTH_CHAT_SYSTEM_PROMPT')
                );
            }
            $this->em->persist($userChat);
        }
        return $userChat;
    }

    /**
     * @param User $user
     * @return UserChat|null
     */
    public function getUserChat(User $user): ?UserChat
    {
        $chatIndex = $user->getCurrentChatIndex();

        if (is_null($chatIndex)) {
            $chatIndex = 1;
            $user->setCurrentChatIndex($chatIndex);
        }
        return $this->userChatRepo->findOneByUserIdAndChatIndex($user->getId(), $chatIndex);
    }

    /**
     * @param User $user
     * @param string|null $name
     * @return UserChat
     */
    public function addNewUserChat(User $user, string $name = null): UserChat {
        $newChatIndex = $this->userChatRepo->getLastChatIndex($user->getId()) + 1;
        return $this->getOrAddUserChat($user, $newChatIndex, $name);
    }

    /**
     * @param string $text
     * @return int|null
     */
    public function parseSelectedChatIndex(string $text): ?int
    {
        $text = str_replace('✅', '', $text);
        return isset(self::CHAT_BUTTONS[$text]) ? self::CHAT_BUTTONS[$text] : null;
    }

    /**
     * @param int $currentChatIndex
     * @return string[]
     */
    public function getChatButtons(int $currentChatIndex): array
    {
        $chatButtons = [];
        foreach (self::CHAT_BUTTONS as $key => $value) {
            if ($value === $currentChatIndex) {
                $chatButtons[$key . '✅'] = $value;
            } else {
                $chatButtons[$key] = $value;
            }
        }
        return array_keys($chatButtons);
    }
}