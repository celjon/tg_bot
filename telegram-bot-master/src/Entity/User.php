<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class User
 * @package App\Entity
 * @ORM\Table(name="users")
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User
{
    public const STATE_SET_PRESENT_USER = 4;
    public const STATE_ADD_TO_CONTEXT = 5;
    public const STATE_SYSTEM_PROMPT = 6;
    public const STATE_CREATE_NEW_CUSTOM_CHAT = 7;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * @var string
     * @ORM\Column(type="text", name="tg_id", nullable=true)
     */
    private $tgId;
    /**
     * @var string
     * @ORM\Column(type="text", name="first_name", nullable=true)
     */
    private $firstName;
    /**
     * @var string
     * @ORM\Column(type="text", name="last_name", nullable=true)
     */
    private $lastName;
    /**
     * @var string
     * @ORM\Column(type="text", name="username", nullable=true)
     */
    private $username;
    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    private $email;
    /**
     * @var string
     * @ORM\Column(type="string", length=2, name="language_code", nullable=true)
     */
    private $languageCode;
    /**
     * @var string
     * @ORM\Column(type="text", name="bothub_id", nullable=true)
     */
    private $bothubId;
    /**
     * @var string
     * @ORM\Column(type="text", name="bothub_group_id", nullable=true)
     */
    private $bothubGroupId;
    /**
     * @var DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", name="registered_at")
     */
    private $registeredAt;
    /**
     * @var string
     * @ORM\Column(type="text", name="bothub_access_token", nullable=true)
     */
    private $bothubAccessToken;
    /**
     * @var DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", name="bothub_access_token_created_at", nullable=true)
     */
    private $bothubAccessTokenCreatedAt;
    /**
     * @var int
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $state;
    /**
     * @var string
     * @ORM\Column(type="text", name="gpt_model", nullable=true)
     */
    private $gptModel;
    /**
     * @var string
     * @ORM\Column(type="text", name="image_generation_model", nullable=true)
     */
    private $imageGenerationModel;
    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    private $tool;
    /**
     * @var string
     * @ORM\Column(type="text", name="present_data", nullable=true)
     */
    private $presentData;
    /**
     * @var integer
     * @ORM\Column(type="smallint", name="current_chat_index")
     */
    private $currentChatIndex;
    /**
     * @var int[]
     * @ORM\Column(type="json", name="system_messages_to_delete", nullable=true)
     */
    private $systemMessagesToDelete;
    /**
     * @var string
     * @ORM\Column(type="text", name="referral_code", nullable=true)
     */
    private $referralCode;
    /**
     * @var Message[]
     * @ORM\OneToMany(targetEntity="Message", mappedBy="user")
     */
    private $messages;
    /**
     * @var UserChat[]
     * @ORM\OneToMany(targetEntity="UserChat", mappedBy="user")
     */
    private $userChats;
    /**
     * @var Present[]
     * @ORM\OneToMany(targetEntity="Present", mappedBy="user")
     */
    private $presents;
    /**
     * @var int
     * @ORM\Column(type="integer", name="current_chat_list_page")
     */
    private $currentChatListPage;

    /**
     * User constructor.
     */
    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->userChats = new ArrayCollection();
        $this->presents = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getTgId(): ?string
    {
        return $this->tgId;
    }

    /**
     * @param string|null $tgId
     * @return User
     */
    public function setTgId(?string $tgId): User
    {
        $this->tgId = $tgId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param string|null $firstName
     * @return User
     */
    public function setFirstName(?string $firstName): User
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param string|null $lastName
     * @return User
     */
    public function setLastName(?string $lastName): User
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string|null $username
     * @return User
     */
    public function setUsername(?string $username): User
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     * @return User
     */
    public function setEmail(?string $email): User
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLanguageCode(): ?string
    {
        return $this->languageCode;
    }

    /**
     * @param string|null $languageCode
     * @return User
     */
    public function setLanguageCode(?string $languageCode): User
    {
        $this->languageCode = $languageCode;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBothubId(): ?string
    {
        return $this->bothubId;
    }

    /**
     * @param string $bothubId
     * @return User
     */
    public function setBothubId(string $bothubId): User
    {
        $this->bothubId = $bothubId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBothubGroupId(): ?string
    {
        return $this->bothubGroupId;
    }

    /**
     * @param string $bothubGroupId
     * @return $this
     */
    public function setBothubGroupId(string $bothubGroupId): User
    {
        $this->bothubGroupId = $bothubGroupId;
        return $this;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getRegisteredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    /**
     * @param DateTimeImmutable $registeredAt
     * @return User
     */
    public function setRegisteredAt(DateTimeImmutable $registeredAt): User
    {
        $this->registeredAt = $registeredAt;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBothubAccessToken(): ?string
    {
        return $this->bothubAccessToken;
    }

    /**
     * @param string|null $bothubAccessToken
     * @return User
     */
    public function setBothubAccessToken(?string $bothubAccessToken): User
    {
        $this->bothubAccessToken = $bothubAccessToken;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getBothubAccessTokenCreatedAt(): ?DateTimeImmutable
    {
        return $this->bothubAccessTokenCreatedAt;
    }

    /**
     * @param DateTimeImmutable $bothubAccessTokenCreatedAt
     * @return User
     */
    public function setBothubAccessTokenCreatedAt(DateTimeImmutable $bothubAccessTokenCreatedAt): User
    {
        $this->bothubAccessTokenCreatedAt = $bothubAccessTokenCreatedAt;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getState(): ?int
    {
        return $this->state;
    }

    /**
     * @param int|null $state
     * @return User
     */
    public function setState(?int $state): User
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return string
     */
    public function getGptModel(): ?string
    {
        return $this->gptModel;
    }

    /**
     * @param string $gptModel
     * @return User
     */
    public function setGptModel(string $gptModel): User
    {
        $this->gptModel = $gptModel;
        return $this;
    }

    /**
     * @return string
     */
    public function getImageGenerationModel(): ?string
    {
        return $this->imageGenerationModel;
    }

    /**
     * @param string $imageGenerationModel
     * @return User
     */
    public function setImageGenerationModel(string $imageGenerationModel): User
    {
        $this->imageGenerationModel = $imageGenerationModel;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTool(): ?string
    {
        return $this->tool;
    }

    /**
     * @param string $tool
     * @return User
     */
    public function setTool(string $tool): User
    {
        $this->tool = $tool;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPresentData(): ?string
    {
        return $this->presentData;
    }

    /**
     * @param string|null $presentData
     * @return User
     */
    public function setPresentData(?string $presentData): User
    {
        $this->presentData = $presentData;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentChatIndex(): ?int
    {
        return $this->currentChatIndex;
    }

    /**
     * @param int $currentChatIndex
     * @return User
     */
    public function setCurrentChatIndex(int $currentChatIndex): User
    {
        $this->currentChatIndex = $currentChatIndex;
        return $this;
    }

    /**
     * @return int[]|null
     */
    public function getSystemMessagesToDelete(): ?array
    {
        return $this->systemMessagesToDelete;
    }

    /**
     * @param int $messageId
     * @return $this
     */
    public function addSystemMessageToDelete(int $messageId): User
    {
        if ($this->systemMessagesToDelete === null) {
            $this->systemMessagesToDelete = [];
        }
        $this->systemMessagesToDelete[] = $messageId;
        return $this;
    }

    /**
     * @return $this
     */
    public function clearSystemMessagesToDelete(): User
    {
        $this->systemMessagesToDelete = null;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getReferralCode(): ?string
    {
        return $this->referralCode;
    }

    /**
     * @param string|null $referralCode
     * @return User
     */
    public function setReferralCode(?string $referralCode): User
    {
        $this->referralCode = $referralCode;
        return $this;
    }

    /**
     * @return Collection|Message[]
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    /**
     * @return Collection|Present[]
     */
    public function getPresents(): Collection
    {
        return $this->presents;
    }

    /**
     * @return int
     */
    public function getCurrentChatListPage(): int
    {
        return $this->currentChatListPage;
    }

    /**
     * @param int $currentChatListPage
     * @return $this
     */
    public function setCurrentChatListPage(int $currentChatListPage): User
    {
        $this->currentChatListPage = $currentChatListPage;
        return $this;
    }
}