<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Model
 * @package App\Entity
 * @ORM\Table(name="models")
 * @ORM\Entity(repositoryClass="App\Repository\ModelRepository")
 */
class Model
{
    /**
     * @var string
     * @ORM\Column(type="text")
     * @ORM\Id
     */
    private $id;
    /**
     * @var string
     * @ORM\Column(type="text")
     */
    private $label;
    /**
     * @var int
     * @ORM\Column(type="integer", name="max_tokens")
     */
    private $maxTokens;
    /**
     * @var array
     * @ORM\Column(type="json", nullable=true)
     */
    private $features;
    /** @var bool */
    private $isAllowed;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return Model
     */
    public function setId(string $id): Model
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @param string|null $label
     * @return Model
     */
    public function setLabel(?string $label): Model
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabelOrId(): string
    {
        return $this->label ?? $this->id;
    }

    /**
     * @return int
     */
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * @param int $maxTokens
     * @return Model
     */
    public function setMaxTokens(int $maxTokens): Model
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function isAllowed(): bool
    {
        return $this->isAllowed;
    }

    public function setIsAllowed(bool $isAllowed): Model
    {
        $this->isAllowed = $isAllowed;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getFeatures(): ?array
    {
        return $this->features;
    }

    /**
     * @param array $features
     * @return Model
     */
    public function setFeatures(array $features): Model
    {
        $this->features = $features;
        return $this;
    }
}