<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\User;
use App\Entity\UserChat;
use App\Exception\BothubException;
use App\Exception\MidjourneyException;
use App\Exception\ProhibitedContentException;
use App\Exception\WhisperException;
use App\Exception\WrongFormatException;
use App\Service\DTO\Bothub\MessageDTO;
use App\Service\DTO\Bothub\ModelDTO;
use App\Service\DTO\Bothub\PlanDTO;
use App\Service\DTO\Bothub\ReferralProgramDTO;
use App\Service\DTO\Bothub\ReferralTemplateDTO;
use App\Service\DTO\Bothub\UserDTO;
use CURLFile;
use DateTimeImmutable;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class BothubService
{
    /** @var BothubApiService */
    private $bothubApi;
    /** @var ParameterBagInterface */
    private $parameterBag;
    /** @var ModelService */
    private $modelService;
    /** @var UserService */
    private $userService;
    /** @var UserChatService */
    private $userChatService;
    /** @var AudioService */
    private $audioService;

    /**
     * BothubService constructor.
     * @param BothubApiService $bothubApi
     * @param ParameterBagInterface $parameterBag
     * @param ModelService $modelService
     * @param UserService $userService
     * @param UserChatService $userChatService
     * @param AudioService $audioService
     */
    public function __construct(
        BothubApiService $bothubApi,
        ParameterBagInterface $parameterBag,
        ModelService $modelService,
        UserService $userService,
        UserChatService $userChatService,
        AudioService $audioService
    ) {
        $this->bothubApi = $bothubApi;
        $this->parameterBag = $parameterBag;
        $this->modelService = $modelService;
        $this->userService = $userService;
        $this->userChatService = $userChatService;
        $this->audioService = $audioService;
    }

    /**
     * @param UserChat $userChat
     * @param bool $imageGeneration
     * @param bool $noRecursion
     * @throws BothubException
     */
    public function createNewChat(UserChat $userChat, bool $imageGeneration = false, bool $noRecursion = false): void
    {
        $name = 'Chat ' . date('Y-m-d H:i:s');
        $user = $userChat->getUser();
        $accessToken = $this->getAccessToken($userChat);
        if (!$user->getBothubGroupId()) {
            $user->setBothubGroupId($this->bothubApi->createNewGroup($accessToken, 'Telegram')['id']);
        }
        try {
            if ($imageGeneration) {
                if (strpos($user->getImageGenerationModel(), 'flux') !== false) {
                    $response = $this->bothubApi->createNewChat($accessToken, $user->getBothubGroupId(), $name, 'replicate-flux');
                    $this->bothubApi->updateParentModel($accessToken, $userChat->getBothubChatId(), 'replicate-flux');
                    $this->bothubApi->saveModel($accessToken, $userChat->getBothubChatId(), $user->getImageGenerationModel());
                    $response['model_id'] = $user->getImageGenerationModel();
                } else {
                    $response = $this->bothubApi->createNewChat($accessToken, $user->getBothubGroupId(), $name, $user->getImageGenerationModel());
                }
                $chatId = $response['id'];
                $modelId = $response['model_id'];
            } else {
                $defaultModel = $this->getDefaultModel($userChat);
                $response = $this->bothubApi->createNewChat($accessToken, $user->getBothubGroupId(), $name, $defaultModel->parentId);
                $chatId = $response['id'];
                $modelId = $defaultModel->id;
                if (($userChat->getBothubChatModel() && $userChat->getBothubChatModel() !== $modelId) || !$userChat->isContextRemember() || $userChat->getSystemPrompt()) {
                    $maxTokens = null;
                    if ($userChat->getBothubChatModel() && !$this->modelService->isImageGenerationModel($userChat->getBothubChatModel())) {
                        $model = $this->modelService->findModelById($userChat->getBothubChatModel());
                        if (!$model) {
                            $userChat->setBothubChatId($chatId)->setBothubChatModel($modelId);
                            return;
                        }
                        $modelId = $model->getId();
                        $maxTokens = floor($model->getMaxTokens() / 2);
                    }
                    $this->bothubApi->saveChatSettings($accessToken, $chatId, $modelId, $maxTokens, $userChat->isContextRemember(), $userChat->getSystemPrompt());
                }
            }
        } catch (BothubException $e) {
            if (($e->getCode() === 404 || $e->getCode() === 500) && !$noRecursion) {
                //Пользователь удалил группу на вебе, создаём новую и создаём чат заново
                $user->setBothubGroupId($this->bothubApi->createNewGroup($accessToken, 'Telegram')['id']);
                $this->createNewChat($userChat, $imageGeneration, true);
                return;
            }
            throw $e;
        }
        $userChat->setBothubChatId($chatId)->setBothubChatModel($modelId);
    }

    /**
     * @param UserChat $userChat
     * @param bool $noRecursion
     * @return void
     * @throws BothubException
     */
    public function createNewDefaultChat(UserChat $userChat, bool $noRecursion = false)
    {
        $name = 'Chat ' . date('Y-m-d H:i:s');
        $user = $userChat->getUser();
        $accessToken = $this->getAccessToken($userChat);
        if (!$user->getBothubGroupId()) {
            $user->setBothubGroupId($this->bothubApi->createNewGroup($accessToken, 'Telegram')['id']);
        }
        try {
            $defaultModel = $this->getDefaultModel($userChat);
            $response = $this->bothubApi->createNewChat($accessToken, $user->getBothubGroupId(), $name, $defaultModel->parentId);
            $chatId = $response['id'];
            $modelId = $defaultModel->id;
        } catch (BothubException $e) {
            if (($e->getCode() === 404 || $e->getCode() === 500) && !$noRecursion) {
                //Пользователь удалил группу на вебе, создаём новую и создаём чат заново
                $user->setBothubGroupId($this->bothubApi->createNewGroup($accessToken, 'Telegram')['id']);
                $this->createNewDefaultChat($userChat, true);
                return;
            }
            throw $e;
        }
        $userChat->setBothubChatId($chatId)->setBothubChatModel($modelId);
    }

    /**
     * @param UserChat $userChat
     * @return void
     * @throws BothubException
     */
    public function resetContext(UserChat $userChat): void
    {
        $accessToken = $this->getAccessToken($userChat);
        $this->bothubApi->resetContext($accessToken, $userChat->getBothubChatId());
    }

    /**
     * @param UserChat $userChat
     * @throws BothubException
     */
    public function saveSystemPrompt(UserChat $userChat): void
    {
        if (!$userChat->getBothubChatId()) {
            $this->createNewChat($userChat);
            return;
        }
        if (!$this->modelService->isGptModel($userChat->getBothubChatModel())) {
            return;
        }
        $accessToken = $this->getAccessToken($userChat);
        $this->bothubApi->saveSystemPrompt($accessToken, $userChat->getBothubChatId(), $userChat->getSystemPrompt());
    }

    /**
     * @param UserChat $userChat
     * @return bool
     * @throws BothubException
     */
    public function getWebSearch(UserChat $userChat): bool
    {
        $accessToken = $this->getAccessToken($userChat);
        if (!$userChat->getBothubChatId()) {
            return false;
        }
        try {
            $webSearch = $this->bothubApi->getWebSearch($accessToken, $userChat->getBothubChatId());
        } catch (Exception $e) {
            $webSearch = false;
        }
        return $webSearch;
    }

    /**
     * @param UserChat $userChat
     * @param bool $value
     * @return void
     * @throws BothubException
     */
    public function enableWebSearch(UserChat $userChat, bool $value): void
    {
        if (!$userChat->getBothubChatId()) {
            $this->createNewChat($userChat);
            return;
        }
        if (!$this->modelService->isGptModel($userChat->getBothubChatModel())) {
            return;
        }

        $accessToken = $this->getAccessToken($userChat);
        $this->bothubApi->enableWebSearch($accessToken, $userChat->getBothubChatId(), $value);
    }

    /**
     * @param UserChat $userChat
     * @param Message $message
     * @param string|null $fileUrl
     * @param string|null $fileName
     * @return MessageDTO
     * @throws BothubException
     * @throws MidjourneyException
     * @throws Exception
     * @throws ProhibitedContentException
     */
    public function sendMessage(UserChat $userChat, Message $message, ?string $fileUrl = null, ?string $fileName = null): MessageDTO
    {
        $lang = new LanguageService($userChat->getUser()->getLanguageCode());
        if (!$userChat->getBothubChatId()) {
            $this->createNewChat($userChat);
        }
        $accessToken = $this->getAccessToken($userChat);
        $file = null;
        if ($fileUrl) {
            $filePath = FileService::saveTempFile($fileUrl, $userChat->getUserId() . '_' . time() . '_');
            $file = new CURLFile($filePath, mime_content_type($filePath), $fileName ?? basename($filePath));
        }
        $prompt = $message->getText();
        if (
            $file &&
            $prompt === "" &&
            $userChat->getContextCounter() === 0 &&
            $userChat->getSystemPrompt() === ""
        ) {
            $prompt = $lang->getLocalizedString("L_EMPTY_PROMPT_WITH_FILE");
        }

        return new MessageDTO($this->bothubApi->sendMessage(
            $accessToken,
            $userChat->getBothubChatId(),
            $prompt,
            [$file]
        ));
    }

    /**
     * @param UserChat $userChat
     * @return MessageDTO
     * @throws BothubException
     * @throws Exception
     * @throws ProhibitedContentException
     */
    public function sendBuffer(UserChat $userChat): MessageDTO
    {
        $texts = [];
        $files = [];
        $fileNames = [];
        foreach ($userChat->getBuffer() as $bufferMessage) {
            if (!empty($bufferMessage['text'])) {
                $text = $bufferMessage['text'];
                if ($userChat->isLinksParse()) {
                    $text = UrlParser::parseUrls($text);
                }
                $texts[] = $text;
            }
            if (!empty($bufferMessage['fileName'])) {
                $fileName = $bufferMessage['fileName'];
                $fileNames[] = $fileName;
                $displayFileName = !empty($bufferMessage['displayFileName']) ? $bufferMessage['displayFileName'] : null;
                $fullFilePath = FileService::getFullBufferFilePath($fileName);
                $files[] = new CURLFile(
                    $fullFilePath,
                    mime_content_type($fullFilePath),
                    $displayFileName ?? basename($fullFilePath)
                );
            }
        }
        if (empty($texts) && empty($fileNames)) {
            return new MessageDTO([]);
        }
        $text = implode(PHP_EOL, $texts);
        if (!$userChat->getBothubChatId()) {
            $this->createNewChat($userChat);
        }
        $accessToken = $this->getAccessToken($userChat);
        try {
            $result = new MessageDTO($this->bothubApi->sendMessage(
                $accessToken,
                $userChat->getBothubChatId(),
                $text,
                $files
            ));
        } catch (Exception $e) {
            FileService::removeBufferFiles($fileNames);
            throw $e;
        }
        FileService::removeBufferFiles($fileNames);
        return $result;
    }

    /**
     * @param UserChat $userChat
     * @param string $buttonId
     * @return MessageDTO
     * @throws BothubException
     * @throws MidjourneyException
     * @throws Exception
     */
    public function clickMidjourneyButton(UserChat $userChat, string $buttonId): MessageDTO
    {
        $accessToken = $this->getAccessToken($userChat);
        $result = $this->bothubApi->clickButton($accessToken, $userChat->getBothubChatId(), $buttonId);
        return new MessageDTO($result);
    }

    /**
     * @param UserChat $userChat
     * @return UserDTO
     * @throws BothubException
     * @throws Exception
     */
    public function getUserInfo(UserChat $userChat): UserDTO
    {
        return new UserDTO($this->bothubApi->getUserInfo($this->getAccessToken($userChat)));
    }

    /**
     * @return PlanDTO[]
     * @throws Exception
     * @throws BothubException
     */
    public function listPlans(): array
    {
        $plans = [];
        foreach ($this->bothubApi->listPlans() as $plan) {
            $plans[] = new PlanDTO($plan);
        }
        return $plans;
    }

    /**
     * @param UserChat $userChat
     * @param string $planId
     * @param string $provider
     * @return string
     * @throws BothubException
     * @throws NonUniqueResultException
     * @throws WrongFormatException
     */
    public function buyPlan(UserChat $userChat, string $planId, string $provider): string
    {
        $user = $userChat->getUser();
        $accessToken = $this->getAccessToken($userChat);
        $presentEmail = null;
        $presentUserId = null;
        $presentUserData = $user->getPresentData();
        if ($presentUserData && FormatValidator::isEmail($presentUserData)) {
            $presentEmail = $presentUserData;
        } elseif ($presentUserData && FormatValidator::isUsername($presentUserData)) {
            $username = str_replace('@', '', $presentUserData);
            $presentUser = $this->userService->getOrAddUser(null, null, null, strtolower($username), null);
            if (!$presentUser->getBothubId()) {
                $this->getAccessToken($this->userChatService->getOrAddUserChat($presentUser));
            }
            $presentUserId = $presentUser->getBothubId();
        } elseif ($presentUserData) {
            throw new WrongFormatException($presentUserData);
        }
        $response = $this->bothubApi->buyPlan($accessToken, $planId, $provider, $presentEmail, $presentUserId);
        return $response['url'];
    }

    /**
     * @param UserChat $userChat
     * @return string
     * @throws BothubException
     */
    public function generateTelegramConnectionLink(UserChat $userChat): string
    {
        $accessToken = $this->getAccessToken($userChat);
        $token = $this->bothubApi->generateTelegramConnectionToken($accessToken)['telegramConnectionToken'];
        $webUrl = $this->parameterBag->get('bothubSettings')['webUrl'];
        return $webUrl . '?telegram-connection-token=' . $token;
    }

    /**
     * @return ModelDTO[]
     * @throws BothubException
     */
    public function listModels(UserChat $userChat): array
    {
        $accessToken = $this->getAccessToken($userChat);
        $models = [];
        foreach ($this->bothubApi->listModels($accessToken) as $model) {
            $models[] = new ModelDTO($model);
        }
        return $models;
    }

    /**
     * @param UserChat $userChat
     * @return ReferralTemplateDTO[]
     * @throws BothubException
     */
    public function listReferralTemplates(UserChat $userChat): array
    {
        $accessToken = $this->getAccessToken($userChat);
        $locale = $userChat->getUser()->getLanguageCode() ?? 'en';
        $response = $this->bothubApi->listReferralTemplates($accessToken, $locale);
        $templates = [];
        foreach ($response['data'] as $data) {
            $templates[] = new ReferralTemplateDTO($data);
        }
        return $templates;
    }

    /**
     * @param UserChat $userChat
     * @param string $templateId
     * @return ReferralProgramDTO
     * @throws BothubException
     */
    public function createReferralProgram(UserChat $userChat, string $templateId): ReferralProgramDTO
    {
        $accessToken = $this->getAccessToken($userChat);
        return new ReferralProgramDTO($this->bothubApi->createReferralProgram($accessToken, $templateId));
    }

    /**
     * @param UserChat $userChat
     * @return ReferralProgramDTO[]
     * @throws BothubException
     */
    public function listReferralPrograms(UserChat $userChat): array
    {
        $accessToken = $this->getAccessToken($userChat);
        $programs = [];
        foreach($this->bothubApi->listReferralPrograms($accessToken) as $data) {
            $programs[] = new ReferralProgramDTO($data);
        }
        return $programs;
    }

    /**
     * @param UserChat $userChat
     * @param string $fileUrl
     * @param bool $video
     * @return string
     * @throws BothubException
     * @throws WhisperException
     */
    public function transcribe(UserChat $userChat, string $fileUrl, bool $video = false): string
    {
        return $this->whisper($userChat, $fileUrl, 'transcriptions', $video);
    }

    /**
     * @param UserChat $userChat
     * @param string $fileUrl
     * @param bool $video
     * @return string
     * @throws BothubException
     * @throws WhisperException
     */
    public function translate(UserChat $userChat, string $fileUrl, bool $video = false): string
    {
        return $this->whisper($userChat, $fileUrl, 'translations', $video);
    }

    /**
     * @param UserChat $userChat
     * @param string $fileUrl
     * @param string $method
     * @param bool $video
     * @return string
     * @throws BothubException
     * @throws WhisperException
     */
    private function whisper(UserChat $userChat, string $fileUrl, string $method, bool $video): string
    {
        try {
            $files = $this->audioService->splitToChunksAndGetCurlFiles($fileUrl, $video);
            $accessToken = $this->getAccessToken($userChat);
            $result = [];
            foreach ($files as $file) {
                $result[] = $this->bothubApi->whisper($accessToken, $file, $method);
            }
            return implode(' ', $result);
        } catch (BothubException $e) {
            throw $e;
        } catch (WhisperException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new WhisperException($e->getMessage() . PHP_EOL . $e->getTraceAsString());
        }
    }

    /**
     * @param UserChat $userChat
     * @param string $text
     * @return string
     * @throws BothubException
     */
    public function speech(UserChat $userChat, string $text): string
    {
        $accessToken = $this->getAccessToken($userChat);
        return $this->bothubApi->speech($accessToken, $text);
    }

    /**
     * @param UserChat $userChat
     * @return ModelDTO
     * @throws BothubException
     */
    private function getDefaultModel(UserChat $userChat): ModelDTO
    {
        $models = $this->listModels($userChat);
        $defaultModel = array_filter(
            $models,
            function ($model) {
                return ($model->isDefault || $model->isAllowed) && $this->modelService->isGptModel($model->id);
            }
        );
        return reset($defaultModel);
    }

    /**
     * @param UserChat $userChat
     * @return string
     * @throws BothubException
     */
    private function getAccessToken(UserChat $userChat): string
    {
        $user = $userChat->getUser();
        $token = $user->getBothubAccessToken();
        $currentTime = new DateTimeImmutable();
        $tokenLifetime = 86390;//24 * 60 * 60 - 10
        if (!$token || $user->getBothubAccessTokenCreatedAt()->getTimestamp() + $tokenLifetime <= $currentTime->getTimestamp()) {
            $response = $this->bothubApi->authorize(
                $user->getTgId(),
                $user->getFirstName() ?? $user->getUsername(),
                $user->getBothubId(),
                $user->getReferralCode()
            );
            $token = $response['accessToken'];
            $user->setBothubAccessToken($token)->setBothubAccessTokenCreatedAt($currentTime);
            if (!$user->getBothubId()) {
                $user->setBothubId($response['user']['id']);
                if (!empty($response['user']['groups'])) {
                    $user->setBothubGroupId($response['user']['groups'][0]['id']);
                    if (!empty($response['user']['groups'][0]['chats'])) {
                        $userChat->setBothubChatId($response['user']['groups'][0]['chats'][0]['id']);
                        if (!empty($response['user']['groups'][0]['chats'][0]['settings']) && (!$response['user']['groups'][0]['chats'][0]['settings']['model'])) {
                            $userChat->setBothubChatModel($response['user']['groups'][0]['chats'][0]['settings']['model']);
                        }
                    }
                }
            }
        }
        if (!$user->getGptModel() && $this->modelService->isGptModel($userChat->getBothubChatModel())) {
            $user->setGptModel($userChat->getBothubChatModel());
        }
        if (!$user->getImageGenerationModel()) {
            if ($this->modelService->isImageGenerationModel($userChat->getBothubChatModel())) {
                $user->setImageGenerationModel($userChat->getBothubChatModel());
            } else {
                $user->setImageGenerationModel($this->modelService->getDefaultImageGenerationModelId());
            }
        }
        if (!$user->getTool()) {
            $user->setTool(ToolService::getDefaultTool());
        }
        return $token;
    }
}