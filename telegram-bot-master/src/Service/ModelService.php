<?php

namespace App\Service;

use App\Entity\Model;
use App\Exception\BothubException;
use App\Repository\ModelRepository;
use App\Service\DTO\Bothub\ModelDTO;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

class ModelService
{
    /** @var string  */
    private const TEXT_GENERATION_MODEL = 'TEXT_TO_TEXT';
    /** @var string  */
    private const IMAGE_GENERATION_MODEL = 'TEXT_TO_IMAGE';
    /** @var string */
    private const DEFAULT_IMAGE_GENERATION_MODEL = 'dall-e';
    /** @var string[] */
    private const FORMULA_TO_IMAGE_MODELS = ['gpt-4o'];
    /** @var ModelRepository */
    private $modelRepo;
    /** @var EntityManagerInterface */
    private $em;

    /**
     * ModelService constructor.
     * @param ModelRepository $modelRepo
     * @param EntityManagerInterface $em
     */
    public function __construct(ModelRepository $modelRepo, EntityManagerInterface $em)
    {
        $this->modelRepo = $modelRepo;
        $this->em = $em;
    }

    /**
     * @param ModelDTO[] $models
     * @return Model[]
     * @throws Exception
     */
    public function updateModels(array $models): array
    {
        $this->removeAllModels();
        $result = [];
        foreach ($models as $model) {
            if ($model->disabled || $model->disabledTelegram) {
                continue;
            }
            $result[] = $this->addModel($model->id, $model->label, $model->maxTokens, $model->isAllowed, $model->features);
        }
        usort($result, function (Model $a, Model $b) {
            if ($a->isAllowed() !== $b->isAllowed()) {
                return $a->isAllowed() ? -1 : 1;
            }

            // Если флаг isAllowed одинаковый, сравниваем по getId
            if ($a->getId() > $b->getId()) {
                return 1;
            } elseif ($a->getId() < $b->getId()) {
                return -1;
            } else {
                return 0;
            }
        });
        return $result;
    }

    /**
     * @param string|null $modelId
     * @return bool
     */
    public function isGptModel(?string $modelId): bool
    {
        if (!$modelId || ToolService::isTool($modelId)) {
            return false;
        }
        $model = $this->modelRepo->find($modelId);
        if ($model) {
            return in_array(self::TEXT_GENERATION_MODEL, $model->getFeatures());
        }
        return false;
    }

    /**
     * @param string|null $modelId
     * @return bool
     */
    public function isFormulaToImageModel(?string $modelId): bool
    {
        return $modelId && in_array($modelId, self::FORMULA_TO_IMAGE_MODELS);
    }

    /**
     * @return string
     */
    public function getDefaultFormulaToImageModel(): string
    {
        return self::FORMULA_TO_IMAGE_MODELS[0];
    }

    /**
     * @param string|null $modelId
     * @return bool
     */
    public function isImageGenerationModel(?string $modelId): bool
    {
        if (!$modelId || ToolService::isTool($modelId)) {
            return false;
        }
        $model = $this->modelRepo->find($modelId);
        if ($model) {
            return in_array(self::IMAGE_GENERATION_MODEL, $model->getFeatures());
        }
        return false;
    }

    /**
     * @return string
     */
    public function getDefaultImageGenerationModelId(): string
    {
        return self::DEFAULT_IMAGE_GENERATION_MODEL;
    }

    /**
     * @param string $modelId
     * @return Model|null
     */
    public function findModelById(string $modelId): ?Model
    {
        return $this->modelRepo->findOneById($modelId);
    }

    /**
     * @param string $id
     * @param string|null $label
     * @param int $maxTokens
     * @param bool $isAllowed
     * @param array $features
     * @return Model
     */
    private function addModel(string $id, ?string $label, int $maxTokens, bool $isAllowed, array $features): Model
    {
        $model = (new Model())
            ->setId($id)
            ->setLabel($label)
            ->setMaxTokens($maxTokens)
            ->setIsAllowed($isAllowed)
            ->setFeatures($features)
        ;
        $this->em->persist($model);
        return $model;
    }

    /**
     * @throws Exception
     */
    private function removeAllModels(): void
    {
        $this->modelRepo->deleteAll();
    }
}