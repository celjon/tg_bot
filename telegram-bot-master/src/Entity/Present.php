<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Present
 * @package App\Entity
 * @ORM\Table(name="presents")
 * @ORM\Entity(repositoryClass="App\Repository\PresentRepository")
 */
class Present
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
     * @ORM\ManyToOne(targetEntity="User", inversedBy="messages")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;
    /**
     * @var int
     * @ORM\Column(type="bigint")
     */
    private $tokens;
    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    private $notified;
    /**
     * @var DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", name="parsed_at")
     */
    private $parsedAt;
    /**
     * @var DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", name="notified_at", nullable=true)
     */
    private $notifiedAt;

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
     * @return Present
     */
    public function setUser(User $user): Present
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return int
     */
    public function getTokens(): int
    {
        return $this->tokens;
    }

    /**
     * @param int $tokens
     * @return Present
     */
    public function setTokens(int $tokens): Present
    {
        $this->tokens = $tokens;
        return $this;
    }

    /**
     * @return bool
     */
    public function isNotified(): bool
    {
        return $this->notified;
    }

    /**
     * @param bool $notified
     * @return Present
     */
    public function setNotified(bool $notified): Present
    {
        $this->notified = $notified;
        return $this;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getParsedAt(): DateTimeImmutable
    {
        return $this->parsedAt;
    }

    /**
     * @param DateTimeImmutable $parsedAt
     * @return Present
     */
    public function setParsedAt(DateTimeImmutable $parsedAt): Present
    {
        $this->parsedAt = $parsedAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getNotifiedAt(): ?DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    /**
     * @param DateTimeImmutable $notifiedAt
     * @return Present
     */
    public function setNotifiedAt(DateTimeImmutable $notifiedAt): Present
    {
        $this->notifiedAt = $notifiedAt;
        return $this;
    }
}