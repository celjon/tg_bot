<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Chat
 * @package App\Entity
 * @ORM\Table(name="users_chats")
 * @ORM\Entity(repositoryClass="App\Repository\UserChatRepository")
 */
class UserChat
{
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="User", inversedBy="userChats")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;
    /**
     * @var int
     * @ORM\Column(type="smallint", name="chat_index")
     */
    private $chatIndex;
    /**
     * @var string
     * @ORM\Column(type="text", name="bothub_chat_id", nullable=true)
     */
    private $bothubChatId;
    /**
     * @var string
     * @ORM\Column(type="text", name="bothub_chat_model", nullable=true)
     */
    private $bothubChatModel;
    /**
     * @var bool
     * @ORM\Column(type="boolean", name="context_remember")
     */
    private $contextRemember;
    /**
     * @var int
     * @ORM\Column(type="integer", name="context_counter")
     */
    private $contextCounter;
    /**
     * @var bool
     * @ORM\Column(type="boolean", name="links_parse")
     */
    private $linksParse;
    /**
     * @var array
     * @ORM\Column(type="json", nullable=true)
     */
    private $buffer;
    /**
     * @var string
     * @ORM\Column(type="text", name="system_prompt")
     */
    private $systemPrompt;
    /**
     * @var bool
     * @ORM\Column(type="boolean", name="formula_to_image")
     */
    private $formulaToImage;
    /**
     * @var bool
     * @ORM\Column(type="boolean", name="answer_to_voice")
     */
    private $answerToVoice;
    /**
     * @var string
     * @ORM\Column(type="text", name="name", nullable=true)
     */
    private $name;

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user->getId();
    }

    /**
     * @param User $user
     * @return UserChat
     */
    public function setUser(User $user): UserChat
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return int
     */
    public function getChatIndex(): int
    {
        return $this->chatIndex;
    }

    /**
     * @param int $chatIndex
     * @return UserChat
     */
    public function setChatIndex(int $chatIndex): UserChat
    {
        $this->chatIndex = $chatIndex;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBothubChatId(): ?string
    {
        return $this->bothubChatId;
    }

    /**
     * @param string $bothubChatId
     * @return UserChat
     */
    public function setBothubChatId(string $bothubChatId): UserChat
    {
        $this->bothubChatId = $bothubChatId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBothubChatModel(): ?string
    {
        return $this->bothubChatModel;
    }

    /**
     * @param string $bothubChatModel
     * @return UserChat
     */
    public function setBothubChatModel(string $bothubChatModel): UserChat
    {
        $this->bothubChatModel = $bothubChatModel;
        return $this;
    }

    /**
     * @return bool
     */
    public function isContextRemember(): bool
    {
        return $this->contextRemember;
    }

    /**
     * @param bool $contextRemember
     * @return UserChat
     */
    public function setContextRemember(bool $contextRemember): UserChat
    {
        $this->contextRemember = $contextRemember;
        return $this;
    }

    /**
     * @return int
     */
    public function getContextCounter(): int
    {
        return $this->contextCounter;
    }

    /**
     * @return UserChat
     */
    public function incrementContextCounter(): UserChat
    {
        if ($this->contextRemember) {
            $this->contextCounter++;
        }
        return $this;
    }

    /**
     * @return UserChat
     */
    public function resetContextCounter(): UserChat
    {
        $this->contextCounter = 0;
        return $this;
    }

    /**
     * @return bool
     */
    public function isLinksParse(): bool
    {
        return $this->linksParse;
    }

    /**
     * @param bool $linksParse
     * @return UserChat
     */
    public function setLinksParse(bool $linksParse): UserChat
    {
        $this->linksParse = $linksParse;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getBuffer(): ?array
    {
        return $this->buffer;
    }

    /**
     * @param string|null $text
     * @param string|null $fileName
     * @param string|null $displayFileName
     * @return UserChat
     */
    public function addToBuffer(?string $text, ?string $fileName, ?string $displayFileName): UserChat
    {
        $bufferMessage = [];
        if ($text) {
            $bufferMessage['text'] = $text;
        }
        if ($fileName) {
            $bufferMessage['fileName'] = $fileName;
        }
        if ($displayFileName) {
            $bufferMessage['displayFileName'] = $displayFileName;
        }
        if (!empty($bufferMessage)) {
            $this->buffer[] = $bufferMessage;
        }
        return $this;
    }

    /**
     * @return UserChat
     */
    public function refreshBuffer(): UserChat
    {
        $this->buffer = null;
        return $this;
    }

    /**
     * @return string
     */
    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    /**
     * @param string $systemPrompt
     * @return UserChat
     */
    public function setSystemPrompt(string $systemPrompt): UserChat
    {
        $this->systemPrompt = $systemPrompt;
        return $this;
    }

    /**
     * @return UserChat
     */
    public function resetSystemPrompt(): UserChat
    {
        return $this->setSystemPrompt('');
    }

    /**
     * @return bool
     */
    public function isFormulaToImage(): bool
    {
        return $this->formulaToImage;
    }

    /**
     * @param bool $formulaToImage
     * @return UserChat
     */
    public function setFormulaToImage(bool $formulaToImage): UserChat
    {
        $this->formulaToImage = $formulaToImage;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAnswerToVoice(): bool
    {
        return $this->answerToVoice;
    }

    /**
     * @param bool $answerToVoice
     * @return UserChat
     */
    public function setAnswerToVoice(bool $answerToVoice): UserChat
    {
        $this->answerToVoice = $answerToVoice;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return UserChat
     */
    public function setName(?string $name): UserChat
    {
        $this->name = $name;
        return $this;
    }
}