<?php

namespace App\Command;

use App\Entity\Message;
use App\Entity\UserChat;
use App\Exception\BothubException;
use App\Exception\GettingFileErrorException;
use App\Exception\MidjourneyException;
use App\Exception\ProhibitedContentException;
use App\Exception\TelegramApiException;
use App\Exception\UrlParserException;
use App\Exception\WhisperException;
use App\Service\BothubService;
use App\Service\DTO\Bothub\MessageAttachmentDTO;
use App\Service\FileService;
use App\Service\FormulaService;
use App\Service\KeyboardService;
use App\Service\LanguageService;
use App\Service\MessageService;
use App\Service\ModelService;
use App\Service\PlanService;
use App\Service\PresentService;
use App\Service\QueueService;
use App\Service\TgBotService;
use App\Service\ToolService;
use App\Service\UrlParser;
use App\Service\UserChatService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ProcessMessagesCommand extends Command
{
    /** @var TgBotService */
    private $tgBotService;
    /** @var MessageService */
    private $messageService;
    /** @var PlanService */
    private $planService;
    /** @var ModelService */
    private $modelService;
    /** @var BothubService */
    private $bothubService;
    /** @var QueueService */
    private $queueService;
    /** @var PresentService */
    private $presentService;
    /** @var UserChatService */
    private $userChatService;
    /** @var KeyboardService */
    private $keyboardService;
    /** @var EntityManagerInterface */
    private $em;
    /** @var LoggerInterface */
    private $logger;
    /** @var ParameterBagInterface */
    private $parameterBag;

    /**
     * ProcessMessagesCommand constructor.
     * @param TgBotService $tgBotService
     * @param MessageService $messageService
     * @param PlanService $planService
     * @param ModelService $modelService
     * @param BothubService $bothubService
     * @param QueueService $queueService
     * @param PresentService $presentService
     * @param UserChatService $userChatService
     * @param EntityManagerInterface $em
     * @param LoggerInterface $logger
     * @param ParameterBagInterface $parameterBag
     * @param string|null $name
     */
    public function __construct(
        TgBotService $tgBotService,
        MessageService $messageService,
        PlanService $planService,
        ModelService $modelService,
        BothubService $bothubService,
        QueueService $queueService,
        PresentService $presentService,
        UserChatService $userChatService,
        KeyboardService $keyboardService,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        $this->tgBotService = $tgBotService;
        $this->messageService = $messageService;
        $this->planService = $planService;
        $this->modelService = $modelService;
        $this->bothubService = $bothubService;
        $this->queueService = $queueService;
        $this->presentService = $presentService;
        $this->userChatService = $userChatService;
        $this->keyboardService = $keyboardService;
        $this->em = $em;
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('process-messages')
            ->setDescription('Processing Telegram messages')
            ->addArgument('worker', InputArgument::REQUIRED, 'Worker number');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $worker = (int)$input->getArgument('worker');
        if ($worker < 1 || $worker > $this->queueService->getWorkers()) {
            $output->writeln('Invalid worker number');
            return 0;
        }
        foreach ($this->messageService->getMessagesForProcessing($worker) as $message) {
            $this->em->beginTransaction();
            try {
                $user = $message->getUser();
                $userChat = $this->userChatService->getOrAddUserChat($user);
                $lang = new LanguageService($user->getLanguageCode());
                $this->keyboardService->setUser($user);
                $this->keyboardService->setIsWebSearch($this->bothubService->getWebSearch($userChat));
                switch ($message->getType()) {
                    case Message::TYPE_START:
                        $this->createNewDefaultChatIfNotExist($userChat);
                        $content = $lang->getLocalizedString('L_START_MESSAGE');
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        $this->presentService->sendNotifications($user);
                        break;
                    case Message::TYPE_GPT_CONFIG:
                        $content = $lang->getLocalizedString('L_GPT_MODELS_LIST');
                        $models = $this->modelService->updateModels($this->bothubService->listModels($userChat));
                        $keyboard = $this->tgBotService->getGptModelsInlineKeyboard($lang, $userChat->getBothubChatModel(), $models);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        break;
                    case Message::TYPE_IMAGE_GENERATION_CONFIG:
                        $content = $lang->getLocalizedString('L_IMAGE_GENERATION_MODELS_LIST');
                        $models = $this->modelService->updateModels($this->bothubService->listModels($userChat));
                        $keyboard = $this->tgBotService->getImageGenerationModelsInlineKeyboard($lang, $userChat, $models);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        break;
                    case Message::TYPE_CONTEXT_CONFIG:
                        $content = $lang->getLocalizedString('L_CONTEXT_CONFIG');
                        $keyboard = $this->tgBotService->getContextInlineKeyboard($lang, $userChat);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        break;
                    case Message::TYPE_LINKS_PARSING_CONFIG:
                        $content = $lang->getLocalizedString('L_LINKS_PARSING_CONFIG');
                        $keyboard = $this->tgBotService->getLinksParseInlineKeyboard($lang, $userChat);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        break;
                    case Message::TYPE_FORMULA_TO_IMAGE_CONFIG:
                        $content = $lang->getLocalizedString('L_FORMULA_TO_IMAGE_CONFIG');
                        $keyboard = $this->tgBotService->getFormulaToImageInlineKeyboard($lang, $userChat);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        break;
                    case Message::TYPE_ANSWER_TO_VOICE_CONFIG:
                        $content = $lang->getLocalizedString('L_ANSWER_TO_VOICE_CONFIG');
                        $keyboard = $this->tgBotService->getAnswerToVoiceInlineKeyboard($lang, $userChat);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        break;
                    case Message::TYPE_WEB_SEARCH_CONFIG:
                        $this->createNewDefaultChatIfNotExist($userChat);
                        $content = $lang->getLocalizedString('L_WEB_SEARCH');
                        $isWebSearch = $this->bothubService->getWebSearch($userChat);
                        $keyboard = $this->tgBotService->getWebSearchInlineKeyboard($lang, $isWebSearch);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        break;
                    case Message::TYPE_SEND_MESSAGE:
                    case Message::TYPE_VOICE_MESSAGE:
                    case Message::TYPE_VIDEO_MESSAGE:
                    case Message::TYPE_DOCUMENT_MESSAGE:
                    case Message::TYPE_SEND_BUFFER:
                        /** @var MessageAttachmentDTO[] $attachments */
                        $attachments = [];
                        $onlyTranscribe = false;
                        $content = '';
                        $voice = null;
                        $additionalContent = '';
                        $documentUrl = null;
                        $documentName = null;
                        $sendBuffer = $message->getType() === Message::TYPE_SEND_BUFFER;
                        try {
                            if ($message->getType() === Message::TYPE_VOICE_MESSAGE) {
                                if (!empty($message->getData()['message']['voice'])) {
                                    $fileId = $message->getData()['message']['voice']['file_id'];
                                } else {
                                    $fileId = $message->getData()['message']['audio']['file_id'];
                                }
                                $fileUrl = $this->tgBotService->getFileUrl($fileId);
                                if (ToolService::isTranscribeToolSelected($userChat)) {
                                    $text = $this->bothubService->transcribe($userChat, $fileUrl);
                                    $content = $text;
                                    $onlyTranscribe = true;
                                } elseif ($this->modelService->isGptModel($userChat->getBothubChatModel())) {
                                    $text = $this->bothubService->transcribe($userChat, $fileUrl);
                                } else {
                                    $text = trim($this->bothubService->translate($userChat, $fileUrl), '.');
                                }
                                $message->setText($text);
                            } elseif ($message->getType() === Message::TYPE_VIDEO_MESSAGE) {
                                if (!empty($message->getData()['message']['video_note'])) {
                                    $fileId = $message->getData()['message']['video_note']['file_id'];
                                } else {
                                    $fileId = $message->getData()['message']['video']['file_id'];
                                }
                                $fileUrl = $this->tgBotService->getFileUrl($fileId);
                                if (ToolService::isTranscribeToolSelected($userChat)) {
                                    $text = $this->bothubService->transcribe($userChat, $fileUrl, true);
                                    $content = $text;
                                    $onlyTranscribe = true;
                                } elseif ($this->modelService->isGptModel($userChat->getBothubChatModel())) {
                                    $text = $this->bothubService->transcribe($userChat, $fileUrl, true);
                                } else {
                                    $text = trim($this->bothubService->translate($userChat, $fileUrl, true), '.');
                                }
                                $message->setText($text);
                            } elseif ($message->getType() === Message::TYPE_DOCUMENT_MESSAGE) {
                                $fileId = null;
                                if (!empty($message->getData()['message']['caption'])) {
                                    $message->setText($message->getData()['message']['caption']);
                                }
                                if (!empty($message->getData()['message']['document'])) {
                                    $fileId = $message->getData()['message']['document']['file_id'];
                                    $documentName = $message->getData()['message']['document']['file_name'];
                                } else {
                                    $fileSize = 0;
                                    foreach ($message->getData()['message']['photo'] as $photo) {
                                        if ($photo['file_size'] > $fileSize) {
                                            $fileSize = $photo['file_size'];
                                            $fileId = $photo['file_id'];
                                        }
                                    }
                                }
                                if ($fileId) {
                                    $documentUrl = $this->tgBotService->getFileUrl($fileId);
                                }
                            } elseif (!$sendBuffer) {
                                if (ToolService::isTranscribeToolSelected($userChat) || !trim($message->getText())) {
                                    break;
                                }
                                if ($this->modelService->isGptModel($userChat->getBothubChatModel())) {
                                    $text = $message->getText();
                                    if ($userChat->isLinksParse()) {
                                        $text = UrlParser::parseUrls($text);
                                    }
                                    $message->setText($text);
                                }
                            }
                            $onlyTextToVoice = ToolService::isVoiceToolSelected($userChat);
                            if ($onlyTextToVoice) {
                                $voice = $this->bothubService->speech($userChat, $message->getText());
                            } elseif (!$onlyTranscribe) {
                                if ($sendBuffer && empty($userChat->getBuffer())) {
                                    $content = $lang->getLocalizedString('L_EMPTY_CONTEXT');
                                    $keyboard = $this->tgBotService->getBufferKeyboard($lang);
                                    $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                                    break;
                                }
                                try {
                                    $bothubResponse = $sendBuffer
                                        ? $this->bothubService->sendBuffer($userChat)
                                        : $this->bothubService->sendMessage($userChat, $message, $documentUrl, $documentName);
                                } catch (BothubException $e) {
                                    if ($e->getMessage() === BothubException::CHAT_NOT_FOUND) {
                                        $this->bothubService->createNewChat($userChat);
                                        $userChat->resetContextCounter();
                                        $userChat->incrementContextCounter();
                                        $bothubResponse = $sendBuffer
                                            ? $this->bothubService->sendBuffer($userChat)
                                            : $this->bothubService->sendMessage($userChat, $message, $documentUrl, $documentName);
                                    } else {
                                        throw $e;
                                    }
                                }
                                $userChat->refreshBuffer();
                                $content = $bothubResponse->content;
                                if ($content) {
                                    if ($userChat->isAnswerToVoice()) {
                                        $voice = $this->bothubService->speech($userChat, $content);
                                    } else {
                                        if ($userChat->isFormulaToImage()) {
                                            $output->writeln($content . PHP_EOL . PHP_EOL . PHP_EOL);
                                            $content = FormulaService::formatFormulas($content);
                                            $output->writeln($content . PHP_EOL . PHP_EOL . PHP_EOL);
                                        }
                                        $content = preg_replace_callback('/(```[\s\S]+?```|`[^`]+`)|([_*])/', function ($matches) {
                                            if (!empty($matches[1])) {
                                                return $matches[1];
                                            }

                                            return '\\' . $matches[2];
                                        }, $content);
                                        if ($bothubResponse->tokens) {
                                            $additionalContent = "`-" . number_format($bothubResponse->tokens, 0, '.', ' ') . " caps`";
                                            if ($userChat->isContextRemember()) {
                                                $additionalContent .= $lang->getLocalizedString('L_CONTINUE', ['/continue']);
                                                $additionalContent .= $lang->getLocalizedString('L_RESET_CONTEXT', ['/reset']);
                                            }
                                        }
                                    }
                                }
                                $attachments = $bothubResponse->attachments;
                            }
                        } catch (ProhibitedContentException $e) {
                            $content = $lang->getLocalizedString('L_MODERATE_PROHIBITED_CONTENT', [$e->getMessage()]);
                            $this->logger->error($e->getMessage());
                        } catch (WhisperException $e) {
                            $content = $lang->getLocalizedString('L_ERROR_VOICE_TRANSCRIPTION');
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        } catch (GettingFileErrorException $e) {
                            $content = $lang->getLocalizedString('L_ERROR_TOKEN_LIMIT_EXCEEDED');
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        } catch (UrlParserException $e) {
                            $content = $lang->getLocalizedString('L_ERROR_URL_PARSE_ERROR', [$e->getUrl()]);
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        } catch (BothubException $e) {
                            if (in_array($e->getMessage(), [BothubException::INVALID_MODEL, BothubException::DEFAULT_MODEL_NOT_FOUND])) {
                                if (!$message->getData() || empty($message->getData()['isRepeat'])) {
                                    $data = $message->getData() ?? [];
                                    $data['isRepeat'] = true;
                                    $this->tgBotService->setWorkerForMessage(
                                        $user,
                                        $message->getMessageId(),
                                        $message->getChatId(),
                                        $message->getText(),
                                        $message->getType(),
                                        $data
                                    );
                                } else {
                                    $content = $this->newChatWithDefaultModelWhenError($userChat, $lang);
                                }
                            } else {
                                $content = $lang->getLocalizedErrorString($e->getMessage());
                                $this->logger->error($e->getMessage(), ['exception' => $e]);
                            }
                        } catch (MidjourneyException $e) {
                            $content = '⛔ ' . $e->getMessage();
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        }
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        if ($voice) {
                            $this->tgBotService->sendVoice($user, $message->getChatId(), $voice, null, $message);
                        } elseif ($attachments && $this->modelService->isImageGenerationModel($userChat->getBothubChatModel())) {
                            foreach ($attachments as $attachment) {
                                if ($attachment->file && $attachment->file->type === 'IMAGE') {
                                    $url = $attachment->file->url ?? 'https://storage.bothub.chat/bothub-storage/' . $attachment->file->path;
                                    if ($attachment->buttons[0]->type === 'MJ_BUTTON') {
                                        $mjButtons = array_filter($attachment->buttons, function ($button) {
                                            return $button->type === 'MJ_BUTTON';
                                        });
                                        $keyboard = $this->tgBotService->getMidjourneyInlineKeyboard($lang, $mjButtons);
                                        $this->tgBotService->sendImage($user, $message->getChatId(), $url, null, $message, $keyboard, true);
                                    } else {
                                        $this->tgBotService->sendImage($user, $message->getChatId(), $url, null, $message, $keyboard);
                                    }
                                    sleep(1);
                                }
                            }
                        } elseif ($content) {
                            $contentParts = explode("\n", $content);
                            $content = '';
                            $firstMessage = true;
                            for ($i = 0; $i < count($contentParts); $i++) {
                                if (!$contentParts[$i]) {
                                    continue;
                                } elseif ($userChat->isFormulaToImage() && $formula = FormulaService::parseFormula($contentParts[$i])) {
                                    if ($content) {
                                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $firstMessage ? $message : null, $keyboard);
                                        $content = '';
                                        $firstMessage = false;
                                    }
                                    $this->tgBotService->sendFormula($user, $message->getChatId(), $formula, null, $firstMessage ? $message : null, $keyboard);
                                    $firstMessage = false;
                                    continue;
                                }
                                $updatedContent = trim($content . "\n\n" . $contentParts[$i]);
                                if (mb_strlen($updatedContent) < 4096) {
                                    $content = $updatedContent;
                                } else {
                                    $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $firstMessage ? $message : null, $keyboard);
                                    $firstMessage = false;
                                    $content = trim($contentParts[$i]);
                                }
                            }
                            if ($content) {
                                if ($userChat->isFormulaToImage() && $formula = FormulaService::parseFormula($content)) {
                                    $this->tgBotService->sendFormula($user, $message->getChatId(), $formula, null, $firstMessage ? $message : null, $keyboard);
                                } else {
                                    $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $firstMessage ? $message : null, $keyboard);
                                }
                            }
                            if ($additionalContent) {
                                $this->tgBotService->sendMessage($user, $message->getChatId(), $additionalContent, null, $keyboard);
                            }
                            if ($userChat->getContextCounter() && !($userChat->getContextCounter() % 10)) {
                                $content = $lang->getLocalizedString('L_CONTEXT_NOTIFICATION');
                                $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                            }
                        }
                        break;
                    case Message::TYPE_GET_USER_INFO:
                        try {
                            $bothubResponse = $this->bothubService->getUserInfo($userChat);
                            $content = $lang->getLocalizedString('L_USER_INFO', [
                                $bothubResponse->subscription->plan->type,
                                $bothubResponse->subscription->paymentPlan === 'DEBIT' ? $bothubResponse->subscription->balance : $bothubResponse->subscription->creditLimit,
                                $userChat->getBothubChatModel(),
                                $userChat->getChatIndex()
                            ]);
                        } catch (BothubException $e) {
                            $content = $lang->getLocalizedErrorString($e->getMessage());
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        }
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_REFERRAL:
                        try {
                            $programs = $this->bothubService->listReferralPrograms($userChat);
                            $content = $lang->getLocalizedString(empty($programs)
                                ? 'L_NO_REFERRAL_PROGRAMS'
                                : 'L_YOUR_REFERRAL_PROGRAMS'
                            );
                            $keyboard = $this->tgBotService->getListReferralTemplatesInlineKeyboard($lang);
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, null, $keyboard);
                            $keyboard = $this->keyboardService->getMainKeyboard($lang);
                            foreach ($programs as $program) {
                                $content = $lang->getLocalizedString('L_REFERRAL_PROGRAM', [
                                    $program->template->name,
                                    $this->parameterBag->get('bothubSettings')['webUrl'] . '?invitedBy=' . $program->code,
                                    'https://t.me/' . $this->parameterBag->get('telegramSettings')['botName'] . '?start=' . $program->code,
                                    $program->code,
                                    $program->participants,
                                ]);
                                $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                            }
                        } catch (BothubException $e) {
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                            $content = $lang->getLocalizedErrorString($e->getMessage());
                            $keyboard = $this->keyboardService->getMainKeyboard($lang);
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        }
                        break;
                    case Message::TYPE_LIST_REFERRAL_TEMPLATES:
                        try {
                            $templates = $this->bothubService->listReferralTemplates($userChat);
                            $content = $lang->getLocalizedString('L_SELECT_REFERRAL_TEMPLATE');
                            $keyboard = $this->keyboardService->getMainKeyboard($lang);
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                            foreach ($templates as $template) {
                                $keyboard = $this->tgBotService->getCreateReferralProgramInlineKeyboard($lang, $template->id);
                                $content = $lang->getLocalizedString('L_REFERRAL_TEMPLATE', [
                                    $template->name,
                                    $template->currency,
                                    $template->encouragePercentage,
                                    $template->minWithdrawAmount,
                                    $template->currency,
                                    $template->plan->type,
                                    $template->tokens,
                                ]);
                                $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                            }
                        } catch (BothubException $e) {
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                            $content = $lang->getLocalizedErrorString($e->getMessage());
                            $keyboard = $this->keyboardService->getMainKeyboard($lang);
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        }
                        break;
                    case Message::TYPE_CREATE_REFERRAL_PROGRAM:
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        try {
                            $program = $this->bothubService->createReferralProgram($userChat, $message->getText());
                            $programCreated = $lang->getLocalizedString('L_REFERRAL_PROGRAM_CREATED');
                            $content = $programCreated . $lang->getLocalizedString('L_REFERRAL_PROGRAM', [
                                $program->template->name,
                                $this->parameterBag->get('bothubSettings')['webUrl'] . '?invitedBy=' . $program->code,
                                'https://t.me/' . $this->parameterBag->get('telegramSettings')['botName'] . '?start=' . $program->code,
                                $program->code,
                                $program->participants,
                            ]);
                        } catch (BothubException $e) {
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                            $content = $lang->getLocalizedErrorString($e->getMessage());
                        }
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_CONNECT_TELEGRAM:
                        if ($user->getEmail()) {
                            $content = $lang->getLocalizedErrorString('ACCOUNT_ALREADY_LINKED');
                        } else {
                            try {
                                $link = $this->bothubService->generateTelegramConnectionLink($userChat);
                                $content = $lang->getLocalizedString('L_TELEGRAM_CONNECTION_LINK', [$link]);
                            } catch (BothubException $e) {
                                $content = $lang->getLocalizedErrorString($e->getMessage());
                                $this->logger->error($e->getMessage(), ['exception' => $e]);
                            }
                        }
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_SET_CHAT_NAME:
                        $text = $message->getText();
                        $text = trim($text);
                        $maxChatName = 50;
                        if ($text && strlen($text) > $maxChatName) {
                            $content = $lang->getLocalizedString('L_ERROR_TOO_LONG_CHAT_NAME');
                            $keyboard = $this->tgBotService->getCreateNewCustomChatKeyboard($lang);
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                            break;
                        }
                        $this->userChatService->addNewUserChat($user, $text);
                        $content = $lang->getLocalizedString('L_SUCCESS_CREATE_NEW_CUSTOM_CHAT');
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $user->setState(null);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        break;
                    case Message::TYPE_CREATE_NEW_CUSTOM_CHAT_WITHOUT_NAME:
                        $this->userChatService->addNewUserChat($user);
                        $content = $lang->getLocalizedString('L_SUCCESS_CREATE_NEW_CUSTOM_CHAT');
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $user->setState(null);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        break;
                    case Message::TYPE_CREATE_NEW_CHAT:
                        try {
                            $this->bothubService->createNewChat($userChat);
                            $model = $userChat->getBothubChatModel();
                            if (!$this->modelService->isFormulaToImageModel($model)) {
                                $userChat->setFormulaToImage(false);
                            }
                            $content = $lang->getLocalizedString('L_NEW_CHAT_STARTED', [
                                $model,
                                $userChat->isContextRemember()
                                    ? $lang->getLocalizedString('L_CONTEXT_ENABLED_SHORT')
                                    : $lang->getLocalizedString('L_CONTEXT_DISABLED_SHORT')
                            ]);
                            $content .= $userChat->isContextRemember() ? $lang->getLocalizedString('L_CHAT_SELECTED_CONTEXT_LENGTH', [
                                $userChat->getContextCounter()
                            ]) : '';
                            $content .= $lang->getLocalizedString('L_CHAT_SELECTED_COMMANDS');
                            $currentSystemPrompt = $userChat->getSystemPrompt();
                            if ($currentSystemPrompt) {
                                $content .= $lang->getLocalizedString('L_CURRENT_SYSTEM_PROMPT', [$currentSystemPrompt]);
                            }
                            if (strpos($userChat->getBothubChatModel(), 'gpt-3.5') === 0) {
                                $content .= "\n\n" . $lang->getLocalizedString('L_GPT_NOTIFICATION');
                            }
                        } catch (BothubException $e) {
                            $content = $lang->getLocalizedErrorString($e->getMessage());
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        }
                        $this->keyboardService->setIsWebSearch($this->bothubService->getWebSearch($userChat));
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        break;
                    case Message::TYPE_CREATE_NEW_IMAGE_GENERATION_CHAT:
                        try {
                            $this->bothubService->createNewChat($userChat, true);
                            $content = $lang->getLocalizedString('L_NEW_CHAT_STARTED', [
                                $userChat->getBothubChatModel(),
                                $userChat->isContextRemember()
                                    ? $lang->getLocalizedString('L_CONTEXT_ENABLED_SHORT')
                                    : $lang->getLocalizedString('L_CONTEXT_DISABLED_SHORT')
                            ]);
                            if ($userChat->getBothubChatModel() === 'midjourney') {
                                $content .= "\n\n" . $lang->getLocalizedString('L_MIDJOURNEY_NOTIFICATION');
                            }
                        } catch (BothubException $e) {
                            if ($e->getMessage() === BothubException::DEFAULT_MODEL_NOT_FOUND) {
                                $content = $this->newChatWithDefaultModelWhenError($userChat, $lang);
                                if (!$message->getData() || empty($message->getData()['isRepeat'])) {
                                    $data = $message->getData() ?? [];
                                    $data['isRepeat'] = true;
                                    $this->tgBotService->setWorkerForMessage(
                                        $user,
                                        $message->getMessageId(),
                                        $message->getChatId(),
                                        $message->getText(),
                                        $message->getType(),
                                        $data
                                    );
                                }
                            } else {
                                $content = $lang->getLocalizedErrorString($e->getMessage());
                                $this->logger->error($e->getMessage(), ['exception' => $e]);
                            }
                        }
                        $this->keyboardService->setIsWebSearch($this->bothubService->getWebSearch($userChat));
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_TOOLZ:
                        $tool = $userChat->getBothubChatModel();
                        $content = $lang->getLocalizedString('L_TOOL_WORK_STARTED', [$tool]);
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_CHAT_LIST:
                        $content = $lang->getLocalizedString('L_CUSTOM_CHAT');
                        $page = $user->getCurrentChatListPage();
                        $keyboard = $this->tgBotService->getChatListInlineKeyboard($lang, $user, $page);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        break;
                    case Message::TYPE_LIST_PLANS:
                        $plans = $this->planService->updatePlans($this->bothubService->listPlans());
                        $content = $lang->getLocalizedString('L_SELECT_PLAN');
                        $keyboard = $this->tgBotService->getPlansInlineKeyboard($lang, $plans);
                        $this->tgBotService->clearSystemMessages($user);
                        $systemMessage = $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, null, $keyboard);
                        $user->addSystemMessageToDelete($systemMessage->getMessageId());
                        break;
                    case Message::TYPE_SELECT_PAYMENT_METHOD:
                        $paymentData = $this->tgBotService->checkPaymentMethodButton($lang, $message->getText());
                        if ($paymentData !== null) {
                            try {
                                $paymentUrl = $this->bothubService->buyPlan($userChat, $paymentData->planId, $paymentData->provider);
                                $paymentUrl = str_replace('_', '\\_', $paymentUrl);
                                $content = $lang->getLocalizedString('L_FOLLOW_PAYMENT_LINK', [$paymentUrl]);
                                $keyboard = $this->keyboardService->getMainKeyboard($lang);
                                $this->tgBotService->clearSystemMessages($user);
                                $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                                break;
                            } catch (BothubException $e) {
                                $this->logger->error($e->getMessage(), ['exception' => $e]);

                                if ($e->getMessage() === BothubException::USER_NOT_FOUND) {
                                    $this->tgBotService->clearSystemMessages($user);
                                    $content = $lang->getLocalizedString('L_PRESENT_USER_NOT_FOUND');
                                    $keyboard = $this->keyboardService->getMainKeyboard($lang);
                                    $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                                    break;
                                }

                                $plans = $this->planService->updatePlans($this->bothubService->listPlans());
                                $content = $lang->getLocalizedErrorString($e->getMessage());
                                $keyboard = $this->tgBotService->getPlansInlineKeyboard($lang, $plans);
                                $this->tgBotService->clearSystemMessages($user);
                            }
                        } else {
                            $plans = $this->planService->updatePlans($this->bothubService->listPlans());
                            $content = $lang->getLocalizedErrorString('PLAN_NOT_AVAILABLE');
                            $keyboard = $this->tgBotService->getPlansInlineKeyboard($lang, $plans);
                            $this->tgBotService->clearSystemMessages($user);
                        }
                        $systemMessage = $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, null, $keyboard);
                        $user->addSystemMessageToDelete($systemMessage->getMessageId());
                        break;
                    case Message::TYPE_PRESENT:
                        $content = $lang->getLocalizedString('L_SET_PRESENT_USER');
                        $keyboard = $this->tgBotService->getCancelKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        break;
                    case Message::TYPE_CANCEL_BUY_PLAN:
                    case Message::TYPE_CANCEL_ADD_TO_CONTEXT:
                    case Message::TYPE_CANCEL_SET_SYSTEM_PROMPT:
                    case Message::TYPE_CANCEL_CREATE_NEW_CUSTOM_CHAT:
                        $content = $lang->getLocalizedString('L_CONTINUE_CHATTING');
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        break;
                    case Message::TYPE_RESET_SYSTEM_PROMPT:
                    case Message::TYPE_SAVE_SYSTEM_PROMPT:
                        $this->bothubService->saveSystemPrompt($userChat);
                        $content = $lang->getLocalizedString('L_CONTINUE_CHATTING');
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        break;
                    case Message::TYPE_CHANGE_WEB_SEARCH:
                        try {
                            $this->createNewDefaultChatIfNotExist($userChat);
                            $changeWebSearch = !$this->bothubService->getWebSearch($userChat);
                            $this->bothubService->enableWebSearch($userChat, $changeWebSearch);
                            $this->keyboardService->setIsWebSearch($changeWebSearch);
                            $keyboard = $this->keyboardService->getMainKeyboard($lang);
                            $content = $lang->getLocalizedString('L_WEB_SEARCH_CHANGED');
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        } catch (BothubException $e) {
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        }
                        break;
                    case Message::TYPE_ADD_TO_CONTEXT:
                        if (!$this->modelService->isGptModel($userChat->getBothubChatModel())) {
                            $content = $lang->getLocalizedString('L_ERROR_INVALID_MODEL_FOR_BUFFER', [$userChat->getBothubChatModel()]);
                            $keyboard = $this->keyboardService->getMainKeyboard($lang);
                            $user->setState(null);
                        } else {
                            $content = $lang->getLocalizedString('L_ADD_TO_CONTEXT');
                            $keyboard = $this->tgBotService->getBufferKeyboard($lang);
                        }
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_BUFFER_MESSAGE:
                        $text = $message->getText();
                        if (!$text && !empty($message->getData()['message']['caption'])) {
                            $text = $message->getData()['message']['caption'];
                        }
                        $fileId = null;
                        $fileName = null;
                        $displayFileName = null;
                        try {
                            if (!empty($message->getData()['message']['voice'])) {
                                $voiceFileId = $message->getData()['message']['voice']['file_id'];
                                $fileUrl = $this->tgBotService->getFileUrl($voiceFileId);
                                $text = $this->bothubService->transcribe($userChat, $fileUrl);
                            } elseif (!empty($message->getData()['message']['audio'])) {
                                $audioFileId = $message->getData()['message']['audio']['file_id'];
                                $fileUrl = $this->tgBotService->getFileUrl($audioFileId);
                                $text = $this->bothubService->transcribe($userChat, $fileUrl);
                            } elseif (!empty($message->getData()['message']['video_note'])) {
                                $videoNoteFileId = $message->getData()['message']['video_note']['file_id'];
                                $fileUrl = $this->tgBotService->getFileUrl($videoNoteFileId);
                                $text = $this->bothubService->transcribe($userChat, $fileUrl, true);
                            } elseif (!empty($message->getData()['message']['video'])) {
                                $videoFileId = $message->getData()['message']['video']['file_id'];
                                $fileUrl = $this->tgBotService->getFileUrl($videoFileId);
                                $text = $this->bothubService->transcribe($userChat, $fileUrl, true);
                            } elseif (!empty($message->getData()['message']['document'])) {
                                $fileId = $message->getData()['message']['document']['file_id'];
                                $displayFileName = $message->getData()['message']['document']['file_name'];
                            } elseif (!empty($message->getData()['message']['photo'])) {
                                $fileSize = 0;
                                foreach ($message->getData()['message']['photo'] as $photo) {
                                    if ($photo['file_size'] > $fileSize) {
                                        $fileSize = $photo['file_size'];
                                        $fileId = $photo['file_id'];
                                    }
                                }
                            }
                            if ($fileId) {
                                $fileName = FileService::saveBufferFile($this->tgBotService->getFileUrl($fileId), $user->getId() . '_' . time() . '_');
                            }
                            $userChat->addToBuffer($text, $fileName, $displayFileName);
                        } catch (WhisperException $e) {
                            $content = $lang->getLocalizedString('L_ERROR_VOICE_TRANSCRIPTION');
                            $keyboard = $this->tgBotService->getBufferKeyboard($lang);
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        } catch (GettingFileErrorException $e) {
                            $content = $lang->getLocalizedString('L_ERROR_TOKEN_LIMIT_EXCEEDED');
                            $keyboard = $this->tgBotService->getBufferKeyboard($lang);
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                            $this->logger->error($e->getMessage(), ['exception' => $e]);
                        }
                        break;
                    case Message::TYPE_SET_SYSTEM_PROMPT:
                        $content = $lang->getLocalizedString('L_SET_SYSTEM_PROMPT');
                        $currentSystemPrompt = $userChat->getSystemPrompt();
                        if ($currentSystemPrompt) {
                            $content .= $lang->getLocalizedString('L_CURRENT_SYSTEM_PROMPT', [$currentSystemPrompt]);
                        }
                        $keyboard = $this->tgBotService->getSystemPromptKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_SELECT_CHAT:
                        $this->createNewDefaultChatIfNotExist($userChat);
                        $content = $lang->getLocalizedString('L_CHAT_SELECTED', [
                            $user->getCurrentChatIndex(),
                            $userChat->getBothubChatModel(),
                            $userChat->isContextRemember()
                                ? $lang->getLocalizedString('L_CONTEXT_ENABLED_SHORT')
                                : $lang->getLocalizedString('L_CONTEXT_DISABLED_SHORT')
                        ]);
                        $content .= $userChat->isContextRemember() ? $lang->getLocalizedString('L_CHAT_SELECTED_CONTEXT_LENGTH', [
                            $userChat->getContextCounter()
                        ]) : '';
                        $content .= $lang->getLocalizedString('L_CHAT_SELECTED_COMMANDS');
                        $currentSystemPrompt = $userChat->getSystemPrompt();
                        if ($currentSystemPrompt) {
                            $content .= $lang->getLocalizedString('L_CURRENT_SYSTEM_PROMPT', [$currentSystemPrompt]);
                        }
                        $this->keyboardService->setIsWebSearch($this->bothubService->getWebSearch($userChat));
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_PRIVACY:
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $contentParts = explode("\n", $lang->getLocalizedString('L_PRIVACY'));
                        $content = '';
                        $firstMessage = true;
                        for ($i = 0; $i < count($contentParts); $i++) {
                            if (!$contentParts[$i]) {
                                continue;
                            }
                            $updatedContent = trim($content . "\n\n" . $contentParts[$i]);
                            if (mb_strlen($updatedContent) < 4096) {
                                $content = $updatedContent;
                            } else {
                                $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $firstMessage ? $message : null, $keyboard);
                                $firstMessage = false;
                                $content = trim($contentParts[$i]);
                            }
                        }
                        if ($content) {
                            $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $firstMessage ? $message : null, $keyboard);
                        }
                        break;
                    case Message::TYPE_RESET_CONTEXT:
                        $this->bothubService->resetContext($userChat);
                        $content = $lang->getLocalizedString('L_CONTEXT_RESET');
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                        break;
                    case Message::TYPE_MIDJOURNEY_BUTTONS:
                        $keyboard = $this->keyboardService->getMainKeyboard($lang);
                        $buttonId = $message->getData()['buttonId'];
                        $content = $lang->getLocalizedString('L_WAIT_FOR_MIDJOURNEY_REPLY', [$userChat->getBothubChatModel()]);
                        $this->tgBotService->sendMessage($user, $message->getChatId(), $content, null, $keyboard);
                        $result = $this->bothubService->clickMidjourneyButton($userChat, $buttonId);
                        foreach ($result->attachments as $attachment) {
                            if ($attachment->file && $attachment->file->type === 'IMAGE') {
                                $url = $attachment->file->url ?? 'https://storage.bothub.chat/bothub-storage/' . $attachment->file->path;
                                if ($attachment->buttons[0]->type === 'MJ_BUTTON') {
                                    $mjButtons = array_filter($attachment->buttons, function ($button) {
                                        return $button->type === 'MJ_BUTTON';
                                    });
                                    $keyboard = $this->tgBotService->getMidjourneyInlineKeyboard($lang, $mjButtons);
                                    $this->tgBotService->sendImage($user, $message->getChatId(), $url, null, $message, $keyboard, true);
                                } else {
                                    $this->tgBotService->sendImage($user, $message->getChatId(), $url, null, $message, $keyboard);
                                }
                                sleep(1);
                            }
                        }
                        break;
                }
                $message->setStatus(Message::STATUS_PROCESSED);
                $this->em->flush();
                $this->em->commit();
                $output->writeln('Message ' . $message->getId() . ' processed at ' . time());
            } catch (TelegramApiException $e) {
                $message->setStatus(Message::STATUS_PROCESSED);
                $this->em->flush();
                $this->em->commit();
                $output->writeln('Error on message ' . $message->getId() . ': ' . $e->getMessage());
                $output->writeln($e->getTraceAsString());
                $this->logger->error($e->getMessage(), ['exception' => $e]);
            } catch (BothubException $e) {
                $message->setStatus(Message::STATUS_PROCESSED);
                $this->em->flush();
                $this->em->commit();
                $output->writeln('Error on message ' . $message->getId() . ': ' . $e->getMessage());
                $output->writeln($e->getTraceAsString());
                $this->logger->error($e->getMessage(), ['exception' => $e]);
            } catch (Exception $e) {
                $this->em->rollback();
                $output->writeln('Error on message ' . $message->getId() . ': ' . $e->getMessage());
                $output->writeln($e->getTraceAsString());
                $this->logger->error($e->getMessage(), ['exception' => $e]);
                break;
            }
        }
        return 0;
    }

    /**
     * @param UserChat $userChat
     * @return void
     * @throws BothubException
     */
    private function createNewDefaultChatIfNotExist(UserChat $userChat): void
    {
        if (!$userChat->getBothubChatId()) {
            $this->bothubService->createNewDefaultChat($userChat);
            $model = $userChat->getBothubChatModel();
            if (!$this->modelService->isFormulaToImageModel($model)) {
                $userChat->setFormulaToImage(false);
            }
        }
    }

    /**
     * @param UserChat $userChat
     * @param LanguageService $lang
     * @return string
     * @throws BothubException
     */
    private function newChatWithDefaultModelWhenError(UserChat $userChat, LanguageService $lang): string
    {
        $this->bothubService->createNewDefaultChat($userChat);
        $model = $userChat->getBothubChatModel();
        if (!$this->modelService->isFormulaToImageModel($model)) {
            $userChat->setFormulaToImage(false);
        }
        return $lang->getLocalizedString('L_ERROR_INVALID_MODEL_DEFAULT', [$model]);
    }
}