<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Model;
use App\Entity\Plan;
use App\Entity\User;
use App\Entity\UserChat;
use App\Exception\BothubException;
use App\Exception\GettingFileErrorException;
use App\Exception\InvalidWebhookException;
use App\Exception\TelegramApiException;
use App\Exception\TelegramUpdateException;
use App\Service\DTO\Bothub\ButtonDTO;
use App\Service\DTO\PaymentDataDTO;
use CURLFile;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;
use TelegramBot\Api\InvalidArgumentException;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Types\Update;

class TgBotService
{
    /** @var BotApi */
    private $botApi;
    /** @var string */
    private $webhookSecretToken;
    /** @var int */
    private $alertsChannelId;
    /** @var UserService */
    private $userService;
    /** @var MessageService */
    private $messageService;
    /** @var PlanService */
    private $planService;
    /** @var ModelService */
    private $modelService;
    /** @var QueueService */
    private $queueService;
    /** @var UserChatService */
    private $userChatService;
    /** @var CallbackQueryService */
    private $callbackQueryService;
    /** @var KeyboardService */
    private $keyboardService;
    /** @var BothubService */
    private $bothubService;
    /** @var EntityManagerInterface */
    private $em;

    /**
     * TgBotService constructor.
     * @param ParameterBagInterface $parameterBag
     * @param UserService $userService
     * @param MessageService $messageService
     * @param PlanService $planService
     * @param ModelService $modelService
     * @param QueueService $queueService
     * @param UserChatService $userChatService
     * @param CallbackQueryService $callbackQueryService
     * @param KeyboardService $keyboardService
     * @param BothubService $bothubService
     * @param EntityManagerInterface $em
     * @throws \Exception
     */
    public function __construct(
        ParameterBagInterface  $parameterBag,
        UserService            $userService,
        MessageService         $messageService,
        PlanService            $planService,
        ModelService           $modelService,
        QueueService           $queueService,
        UserChatService        $userChatService,
        CallbackQueryService   $callbackQueryService,
        KeyboardService        $keyboardService,
        BothubService          $bothubService,
        EntityManagerInterface $em
    )
    {
        $telegramSettings = $parameterBag->get('telegramSettings');
        $this->botApi = new BotApi($telegramSettings['token']);
        $this->webhookSecretToken = $telegramSettings['webhookSecretToken'];
        $this->alertsChannelId = $telegramSettings['alertsChannelId'];
        $this->userService = $userService;
        $this->messageService = $messageService;
        $this->planService = $planService;
        $this->modelService = $modelService;
        $this->queueService = $queueService;
        $this->userChatService = $userChatService;
        $this->callbackQueryService = $callbackQueryService;
        $this->keyboardService = $keyboardService;
        $this->bothubService = $bothubService;
        $this->em = $em;
    }

    /**
     * @param string $url
     * @throws Exception
     */
    public function setWebhook(string $url): void
    {
        $this->botApi->setWebhook(
            $url,
            null,
            null,
            40,
            null,
            false,
            $this->webhookSecretToken
        );
    }

    /**
     * @param int $offset
     * @return Update[]
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getUpdates(int $offset): array
    {
        return $this->botApi->getUpdates($offset);
    }

    /**
     * @param string|null $secretTokenHeader
     * @throws InvalidWebhookException
     */
    public function validateWebhook(?string $secretTokenHeader): void
    {
        if ($secretTokenHeader !== $this->webhookSecretToken) {
            throw new InvalidWebhookException();
        }
    }

    /**
     * @param string $json
     * @throws TelegramUpdateException
     * @throws \Exception
     */
    public function parseUpdate(string $json): void
    {
        if (empty($json)) {
            throw new TelegramUpdateException($json);
        }
        $data = json_decode($json, true);
        if (empty($data) || ((
                    empty($data['callback_query']) ||
                    empty($data['callback_query']['data']) ||
                    empty($data['callback_query']['from']) ||
                    empty($data['callback_query']['from']['id'])
                ) && (
                    empty($data['message']) ||
                    empty($data['message']['message_id']) ||
                    empty($data['message']['chat']) ||
                    empty($data['message']['chat']['id']) ||
                    empty($data['message']['from']) ||
                    empty($data['message']['from']['id'])
                )
            )) {
            throw new TelegramUpdateException($json);
        }
        if (isset($data['message']['from'])) {
            $inlineButton = false;
            $from = $data['message']['from'];
            $chatId = $data['message']['chat']['id'];
        } else {
            $inlineButton = true;
            $from = $data['callback_query']['from'];
            $chatId = $data['callback_query']['message']['chat']['id'];
            $callbackQueryId = $data['callback_query']['id'];
            $messageId = $data['callback_query']['message']['message_id'];
        }
        if (isset($from['is_bot']) && $from['is_bot']) {
            return;
        }
        $user = $this->userService->getOrAddUser(
            $from['id'],
            !empty($from['first_name']) ? $from['first_name'] : null,
            !empty($from['last_name']) ? $from['last_name'] : null,
            !empty($from['username']) ? strtolower($from['username']) : null,
            !empty($from['language_code']) ? $from['language_code'] : null
        );
        $this->keyboardService->setUser($user);
        $userChat = $this->userChatService->getUserChat($user);
        $webSearch = $userChat && $this->bothubService->getWebSearch($userChat);
        $this->keyboardService->setIsWebSearch($webSearch);
        $lang = new LanguageService($user->getLanguageCode());
        if ($inlineButton) {
            $message = $data['callback_query']['message'];
            $callbackQuery = $this->callbackQueryService->decode($data['callback_query']['data']);
            $callbackType = $callbackQuery['type'];
            $callbackData = $callbackQuery['data'];
            if ($callbackType === 'cancel') {
                $user->setState(null);
                $content = $lang->getLocalizedString('L_CONTINUE_CHATTING');
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
                $this->clearSystemMessages($user);
            } elseif ($callbackType === 'contextOn') {
                $this->userChatService->getOrAddUserChat($user)->setContextRemember(true)->resetContextCounter();
                $user->setState(null);
                $content = $lang->getLocalizedString('L_CONTEXT_ENABLED_SHORT');
                $this->queueService->setWorkerForMessage($this->messageService->addCreateNewChatMessage(
                    $user,
                    $message['message_id'],
                    $chatId,
                    $callbackType,
                    $message['date']
                ));
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
            } elseif ($callbackType === 'contextOff') {
                $this->userChatService->getOrAddUserChat($user)->setContextRemember(false)->resetContextCounter();
                $user->setState(null);
                $content = $lang->getLocalizedString('L_CONTEXT_DISABLED_SHORT');
                $this->queueService->setWorkerForMessage($this->messageService->addCreateNewChatMessage(
                    $user,
                    $message['message_id'],
                    $chatId,
                    $callbackType,
                    $message['date']
                ));
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
            } elseif ($callbackType === 'linksParseOn') {
                $this->userChatService->getOrAddUserChat($user)->setLinksParse(true);
                $user->setState(null);
                $content = $lang->getLocalizedString('L_LINKS_PARSING_ENABLED');
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
            } elseif ($callbackType === 'linksParseOff') {
                $this->userChatService->getOrAddUserChat($user)->setLinksParse(false);
                $user->setState(null);
                $content = $lang->getLocalizedString('L_LINKS_PARSING_DISABLED');
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
            } elseif ($callbackType === 'formulaToImageOn') {
                $this->userChatService->getOrAddUserChat($user)->setFormulaToImage(true);
                $user->setState(null);
                $content = $lang->getLocalizedString('L_FORMULA_TO_IMAGE_ENABLED');
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
                $userChat = $this->userChatService->getOrAddUserChat($user);
                if (!$this->modelService->isFormulaToImageModel($userChat->getBothubChatModel())) {
                    $model = $this->modelService->getDefaultFormulaToImageModel();
                    $user->setGptModel($model);
                    $userChat->resetContextCounter();
                    $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                        $user,
                        $message['message_id'],
                        $chatId,
                        $model,
                        Message::DIRECTION_REQUEST,
                        Message::TYPE_CREATE_NEW_CHAT,
                        null,
                        (new DateTimeImmutable())->setTimestamp($message['date'])
                    ));
                }
            } elseif ($callbackType === 'formulaToImageOff') {
                $this->userChatService->getOrAddUserChat($user)->setFormulaToImage(false);
                $user->setState(null);
                $content = $lang->getLocalizedString('L_FORMULA_TO_IMAGE_DISABLED');
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
            } elseif ($callbackType === 'answerToVoiceOn') {
                $this->userChatService->getOrAddUserChat($user)->setAnswerToVoice(true);
                $user->setState(null);
                $content = $lang->getLocalizedString('L_ANSWER_TO_VOICE_ENABLED');
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
            } elseif ($callbackType === 'answerToVoiceOff') {
                $this->userChatService->getOrAddUserChat($user)->setAnswerToVoice(false);
                $user->setState(null);
                $content = $lang->getLocalizedString('L_ANSWER_TO_VOICE_DISABLED');
                $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
            } elseif ($callbackType === 'webSearchChange') {
                $user->setState(null);
                $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                    $user,
                    $messageId,
                    $chatId,
                    $callbackType,
                    Message::DIRECTION_REQUEST,
                    Message::TYPE_CHANGE_WEB_SEARCH,
                    null,
                    (new DateTimeImmutable())->setTimestamp($message['date'])
                ));
            } elseif ($callbackType === 'createReferralProgram') {
                $user->setState(null);
                $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                    $user,
                    $message['message_id'],
                    $chatId,
                    $callbackType,
                    Message::DIRECTION_REQUEST,
                    Message::TYPE_LIST_REFERRAL_TEMPLATES,
                    null,
                    (new DateTimeImmutable())->setTimestamp($message['date'])
                ));
            } elseif ($callbackType === 'not_model') {
                $content = $lang->getLocalizedString('L_ERROR_INVALID_MODEL');
                $this->botApi->answerCallbackQuery($callbackQueryId, $content, true);
            } elseif ($callbackType === 'plan_list') {
                $plans = $this->planService->getPlansByType($callbackData['plan_type']);
                $content = $lang->getLocalizedString('L_SELECT_PAYMENT_METHOD');
                $keyboard = $this->getPaymentMethodsInlineKeyboard($lang, $plans);
                $this->clearSystemMessages($user);
                $systemMessage = $this->sendMessage($user, $chatId, $content, null, null, $keyboard);
                $user->addSystemMessageToDelete($systemMessage->getMessageId());
            } elseif ($callbackType === 'plan_payment') {
                $price = $callbackData['price'];
                $currency = $callbackData['currency'];
                $provider = $callbackData['provider'];

                $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                    $user,
                    $message['message_id'],
                    $chatId,
                    $this->getPaymentMethodButton($lang, $price, $currency, $provider),
                    Message::DIRECTION_REQUEST,
                    Message::TYPE_SELECT_PAYMENT_METHOD,
                    null,
                    (new DateTimeImmutable())->setTimestamp($message['date'])
                ));
            } elseif ($callbackType === 'ref_t') {
                $templateId = $callbackData['id'];
                $user->setState(null);
                $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                    $user,
                    $message['message_id'],
                    $chatId,
                    $templateId,
                    Message::DIRECTION_REQUEST,
                    Message::TYPE_CREATE_REFERRAL_PROGRAM,
                    null,
                    (new DateTimeImmutable())->setTimestamp($message['date'])
                ));
            } elseif ($callbackType === 'tool') {
                $tool = $callbackData['tool_id'];
                $user->setTool($tool)->setState(null);
                $this->userChatService->getOrAddUserChat($user)->resetContextCounter()->setBothubChatModel($tool);
                $markup = $this->getChatListInlineKeyboard($lang, $user, $user->getCurrentChatListPage());
                $this->editMessageReplyMarkup($chatId, $messageId, $markup);
                $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                    $user,
                    $message['message_id'],
                    $chatId,
                    $tool,
                    Message::DIRECTION_REQUEST,
                    Message::TYPE_TOOLZ,
                    null,
                    (new DateTimeImmutable())->setTimestamp($message['date'])
                ));
            } elseif ($callbackType === 'chat_models') {
                $model = $callbackData['model_id'];
                if (!$this->modelService->findModelById($model)) {
                    $content = $lang->getLocalizedString('L_ERROR_GPT_MODEL_IS_NOT_AVAILABLE');
                    $user->setState(null);
                    $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
                } else {
                    $user->setGptModel($model)->setState(null);
                    $this->userChatService->getOrAddUserChat($user)->resetContextCounter()->setBothubChatModel($model);
                    $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                        $user,
                        $message['message_id'],
                        $chatId,
                        $model,
                        Message::DIRECTION_REQUEST,
                        Message::TYPE_CREATE_NEW_CHAT,
                        null,
                        (new DateTimeImmutable())->setTimestamp($message['date'])
                    ));
                }
            } elseif ($callbackType === 'image_models') {
                $model = $callbackData['model_id'];
                if (!$this->modelService->findModelById($model)) {
                    $content = $lang->getLocalizedString('L_ERROR_IMAGE_GENERATION_MODEL_IS_NOT_AVAILABLE');
                    $user->setState(null);
                    $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
                } else {
                    $user->setImageGenerationModel($model)->setState(null);
                    $this->userChatService->getOrAddUserChat($user)->resetContextCounter();
                    $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                        $user,
                        $message['message_id'],
                        $chatId,
                        $model,
                        Message::DIRECTION_REQUEST,
                        Message::TYPE_CREATE_NEW_IMAGE_GENERATION_CHAT,
                        null,
                        (new DateTimeImmutable())->setTimestamp($message['date'])
                    ));
                }
            } elseif ($callbackType === 'chat_nav') {
                $page = $callbackData['page'];
                $direction = $callbackData['direction'];
                $nextPage = $page + $direction;
                $user->setCurrentChatListPage($nextPage);
                $markup = $this->getChatListInlineKeyboard($lang, $user, $nextPage);
                $this->editMessageReplyMarkup($chatId, $messageId, $markup);
            } elseif ($callbackType === 'choose_tool') {
                $markup = $this->getToolzInlineButtons($lang, $this->userChatService->getOrAddUserChat($user));
                $this->editMessageReplyMarkup($chatId, $messageId, $markup);
            } elseif ($callbackType === 'create_new_chat') {
                $user->setState(User::STATE_CREATE_NEW_CUSTOM_CHAT);
                $content = $lang->getLocalizedString('L_CUSTOM_CHAT_WARNING');
                $keyboard = $this->getCreateNewCustomChatKeyboard($lang);
                $this->sendMessage($user, $chatId, $content, null, $keyboard);
            } elseif ($callbackType === 'select_chat') {
                $chatIndex = $callbackData['chat_index'];
                $currentPage = $callbackData['current_page'];
                $user->setCurrentChatIndex($chatIndex);
                $this->setWorkerForMessage($user, $messageId, $chatId, $callbackType, Message::TYPE_SELECT_CHAT);
                $markup = $this->getChatListInlineKeyboard($lang, $user, $currentPage);
                $this->editMessageReplyMarkup($chatId, $messageId, $markup);
            } elseif ($callbackType === 'MJ_BUTTON') {
                $buttonId = $callbackData['id'];
                $this->queueService->setWorkerForMessage($this->messageService->addMessage(
                    $user,
                    $message['message_id'],
                    $chatId,
                    $callbackType,
                    Message::DIRECTION_REQUEST,
                    Message::TYPE_MIDJOURNEY_BUTTONS,
                    null,
                    (new DateTimeImmutable())->setTimestamp($message['date']),
                    ['buttonId' => $buttonId]
                ));
            }
            return;
        }
        $messageId = $data['message']['message_id'];
        $text = (!empty($data['message']['text'])) ? $data['message']['text'] : '';
        $saveData = false;
        $isBotCommand = false;
        if (
            !empty($data['message']['entities']) &&
            !empty($data['message']['entities'][0]['type']) &&
            $data['message']['entities'][0]['type'] === 'bot_command'
        ) {
            $isBotCommand = true;
            $userChat = $this->userChatService->getOrAddUserChat($user);
            FileService::clearBufferFiles($userChat->getBuffer());
            $userChat->refreshBuffer();
            if (strpos($text, '/start') === 0) {
                $user->setState(null);
                $type = Message::TYPE_START;
                $startData = explode(' ', $text);
                if (!$user->getBothubId() && count($startData) === 2 && !empty($startData[1])) {
                    $referralCode = $startData[1];
                    $user->setReferralCode($referralCode);
                }
            } else {
                switch ($text) {
                    case '/gpt_config':
                        $user->setState(null);
                        $type = Message::TYPE_GPT_CONFIG;
                        break;
                    case '/image_generation_config':
                        $user->setState(null);
                        $type = Message::TYPE_IMAGE_GENERATION_CONFIG;
                        break;
                    case '/context':
                        $user->setState(null);
                        $type = Message::TYPE_CONTEXT_CONFIG;
                        break;
                    case '/scan_links':
                        $user->setState(null);
                        $type = Message::TYPE_LINKS_PARSING_CONFIG;
                        break;
                    case '/formula':
                        $user->setState(null);
                        $type = Message::TYPE_FORMULA_TO_IMAGE_CONFIG;
                        break;
                    case '/voice':
                        $user->setState(null);
                        $type = Message::TYPE_ANSWER_TO_VOICE_CONFIG;
                        break;
                    case '/profile':
                        $user->setState(null);
                        $type = Message::TYPE_GET_USER_INFO;
                        break;
                    case '/buy_tokens':
                        $user->setState(null)->setPresentData(null);
                        $type = Message::TYPE_LIST_PLANS;
                        break;
                    case '/present':
                        $user->setState(User::STATE_SET_PRESENT_USER)->setPresentData(null);
                        $type = Message::TYPE_PRESENT;
                        break;
                    case '/system_prompt':
                        $user->setState(User::STATE_SYSTEM_PROMPT);
                        $type = Message::TYPE_SET_SYSTEM_PROMPT;
                        break;
                    case '/link_account':
                        $user->setState(null);
                        $type = Message::TYPE_CONNECT_TELEGRAM;
                        break;
                    case '/referral':
                        $user->setState(null);
                        $type = Message::TYPE_REFERRAL;
                        break;
                    case '/privacy':
                        $user->setState(null);
                        $type = Message::TYPE_PRIVACY;
                        break;
                    case '/continue':
                        $user->setState(null);
                        $userChat = $this->userChatService->getOrAddUserChat($user);
                        if ($this->modelService->isGptModel($userChat->getBothubChatModel())) {
                            $userChat->incrementContextCounter();
                            $text = $user->getLanguageCode() === 'ru' ? 'Продолжай' : 'Continue';
                            $type = Message::TYPE_SEND_MESSAGE;
                            try {
                                $this->botApi->sendChatAction($chatId, 'typing');
                            } catch (\Exception $e) {
                            }
                        } else {
                            $type = Message::TYPE_NO_ACTION;
                        }
                        break;
                    case '/reset':
                        $user->setState(null);
                        $this->userChatService->getOrAddUserChat($user)->resetContextCounter();
                        $type = Message::TYPE_RESET_CONTEXT;
                        break;
                    default:
                        $isBotCommand = false;
                        $type = Message::TYPE_NO_ACTION;
                }
            }
        }
        if (!$isBotCommand) {
            if ($user->getState() === User::STATE_SET_PRESENT_USER) {
                $text = trim($text);
                if ($text === $lang->getLocalizedString('L_CANCEL')) {
                    $type = Message::TYPE_CANCEL_BUY_PLAN;
                    $user->setState(null)->setPresentData(null);
                } elseif (FormatValidator::isEmail($text) || FormatValidator::isUsername($text)) {
                    $type = Message::TYPE_LIST_PLANS;
                    $user->setPresentData($text);
                } else {
                    $type = Message::TYPE_NO_ACTION;
                    $content = $lang->getLocalizedString('L_ERROR_INVALID_FORMAT');
                    $this->sendMessage($user, $chatId, $content, null, $this->getCancelKeyboard($lang));
                }
            } elseif ($user->getState() === User::STATE_ADD_TO_CONTEXT) {
                $text = trim($text);
                if ($text === $lang->getLocalizedString('L_CANCEL')) {
                    $type = Message::TYPE_CANCEL_ADD_TO_CONTEXT;
                    $user->setState(null);
                    $userChat = $this->userChatService->getOrAddUserChat($user);
                    FileService::clearBufferFiles($userChat->getBuffer());
                    $userChat->refreshBuffer();
                } elseif ($text === $lang->getLocalizedString('L_BUFFER_KEYBOARD_SEND')) {
                    $type = Message::TYPE_SEND_BUFFER;
                    if (!empty($this->userChatService->getOrAddUserChat($user)->getBuffer())) {
                        $user->setState(null);
                    }
                    try {
                        $this->botApi->sendChatAction($chatId, 'typing');
                    } catch (\Exception $e) {
                    }
                } else {
                    $type = Message::TYPE_BUFFER_MESSAGE;
                    $saveData = true;
                }
            } elseif ($user->getState() === User::STATE_SYSTEM_PROMPT) {
                if ($text === $lang->getLocalizedString('L_CANCEL')) {
                    $type = Message::TYPE_CANCEL_SET_SYSTEM_PROMPT;
                    $user->setState(null);
                } elseif ($text === $lang->getLocalizedString('L_SYSTEM_PROMPT_KEYBOARD_RESET')) {
                    $type = Message::TYPE_RESET_SYSTEM_PROMPT;
                    $user->setState(null);
                    $this->userChatService->getOrAddUserChat($user)->resetSystemPrompt();
                } else {
                    $type = Message::TYPE_SAVE_SYSTEM_PROMPT;
                    $user->setState(null);
                    $this->userChatService->getOrAddUserChat($user)->setSystemPrompt($text);
                }
            } elseif ($user->getState() === User::STATE_CREATE_NEW_CUSTOM_CHAT) {
                $text = trim($text);
                if ($text === $lang->getLocalizedString('L_CANCEL')) {
                    $type = Message::TYPE_CANCEL_CREATE_NEW_CUSTOM_CHAT;
                    $user->setState(null);
                } elseif ($text === $lang->getLocalizedString('L_CREATE_NEW_CUSTOM_CHAT_WITHOUT_NAME')) {
                    $type = Message::TYPE_CREATE_NEW_CUSTOM_CHAT_WITHOUT_NAME;
                } else {
                    $type = Message::TYPE_SET_CHAT_NAME;
                }
            } elseif ($text === $lang->getLocalizedString('L_MAIN_KEYBOARD_NEW_CHAT')) {
                $type = Message::TYPE_CREATE_NEW_CHAT;
                $this->userChatService->getOrAddUserChat($user)->resetContextCounter();
            } elseif (strpos($text, $lang->getLocalizedString('L_MAIN_KEYBOARD_WEB_SEARCH')) === 0) {
                $type = Message::TYPE_CHANGE_WEB_SEARCH;
            } elseif ($text === $lang->getLocalizedString('L_MAIN_KEYBOARD_NEW_IMAGE_GENERATION_CHAT')) {
                $type = Message::TYPE_CREATE_NEW_IMAGE_GENERATION_CHAT;
                $this->userChatService->getOrAddUserChat($user)->resetContextCounter();
            } elseif ($text === $lang->getLocalizedString('L_MAIN_KEYBOARD_TOOLZ')) {
                $type = Message::TYPE_CHAT_LIST;
                $this->userChatService->getOrAddUserChat($user)->resetContextCounter();
            } elseif ($text === $lang->getLocalizedString('L_MAIN_KEYBOARD_BUFFER')) {
                $type = Message::TYPE_ADD_TO_CONTEXT;
                $user->setState(User::STATE_ADD_TO_CONTEXT);
                $userChat = $this->userChatService->getOrAddUserChat($user);
                FileService::clearBufferFiles($userChat->getBuffer());
                $userChat->refreshBuffer();
            } elseif ($selectedChatIndex = $this->userChatService->parseSelectedChatIndex($text)) {
                $type = Message::TYPE_SELECT_CHAT;
                $user->setCurrentChatIndex($selectedChatIndex)->setState(null);
            } else {
                $userChat = $this->userChatService->getOrAddUserChat($user);
                if (!empty($data['message']['voice']) || !empty($data['message']['audio'])) {
                    $type = Message::TYPE_VOICE_MESSAGE;
                    $saveData = true;
                } elseif (!empty($data['message']['photo']) || !empty($data['message']['document'])) {
                    $type = Message::TYPE_DOCUMENT_MESSAGE;
                    $saveData = true;
                } elseif (!empty($data['message']['video_note']) || !empty($data['message']['video'])) {
                    $type = Message::TYPE_VIDEO_MESSAGE;
                    $saveData = true;
                } else {
                    $type = Message::TYPE_SEND_MESSAGE;
                }
                if (!$this->modelService->isImageGenerationModel($userChat->getBothubChatModel())) {
                    $userChat->incrementContextCounter();
                }
                try {
                    if ($this->modelService->isImageGenerationModel($userChat->getBothubChatModel())) {
                        $content = $lang->getLocalizedString('L_WAIT_FOR_MIDJOURNEY_REPLY', [$userChat->getBothubChatModel()]);
                        $this->sendMessage($user, $chatId, $content, null, $this->keyboardService->getMainKeyboard($lang));
                    } elseif (!ToolService::isTranscribeToolSelected($userChat)) {
                        $this->botApi->sendChatAction($chatId, 'typing');
                    }
                } catch (\Exception $e) {
                }
            }
        }
        $this->setWorkerForMessage($user, $messageId, $chatId, $text, $type, $saveData ? $data : null);
    }

    /**
     * @param User $user
     * @param int $messageId
     * @param int $chatId
     * @param string $text
     * @param int $type
     * @param array|null $data
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function setWorkerForMessage(
        User $user,
        int $messageId,
        int $chatId,
        string $text,
        int $type,
        ?array $data = null
    ): void
    {
        $this->queueService->setWorkerForMessage($this->messageService->addMessage(
            $user,
            $messageId,
            $chatId,
            $text,
            Message::DIRECTION_REQUEST,
            $type,
            null,
            (new DateTimeImmutable())->setTimestamp($data['message']['date']),
            $data
        ));
    }

    /**
     * @param User $user
     * @param int $chatId
     * @param string $text
     * @param Message|null $relatedMessage
     * @param array|null $keyboard
     * @param array|null $inlineKeyboard
     * @param string|null $parseMode
     * @param bool $disablePreview
     * @return Message
     * @throws TelegramApiException
     */
    public function sendMessage(
        User     $user,
        int      $chatId,
        string   $text,
        ?Message $relatedMessage,
        ?array   $keyboard = null,
        ?array   $inlineKeyboard = null,
        ?string  $parseMode = 'Markdown',
        bool     $disablePreview = true
    ): Message
    {
        try {
            if ($keyboard) {
                $markup = new ReplyKeyboardMarkup($keyboard, true, true);
            } elseif ($inlineKeyboard) {
                $markup = new InlineKeyboardMarkup($inlineKeyboard);
            } else {
                $markup = null;
            }
            $response = $this->botApi->sendMessage(
                $chatId,
                $text,
                $parseMode,
                $disablePreview,
                null,
                $relatedMessage ? $relatedMessage->getMessageId() : null,
                $markup
            );
            return $this->messageService->addMessage(
                $user,
                $response->getMessageId(),
                $chatId,
                $text,
                Message::DIRECTION_RESPONSE,
                Message::TYPE_NO_ACTION,
                $relatedMessage
            );
        } catch (\Exception $e) {
            throw new TelegramApiException($e->getMessage() . ' (text: ' . $text . ')');
        }
    }

    public function sendPhoto(
        User     $user,
        int      $chatId,
        CURLFile $photo,
        ?string  $caption = null,
        ?Message $relatedMessage = null,
        ?array   $keyboard = null,
        ?string  $parseMode = 'Markdown'
    ): void
    {
        try {
            $response = $this->botApi->sendPhoto(
                $chatId,
                $photo,
                $caption,
                null,
                $relatedMessage ? $relatedMessage->getMessageId() : null,
                !empty($keyboard) ? new ReplyKeyboardMarkup($keyboard, true, true) : null,
                false,
                $parseMode
            );
            $this->messageService->addMessage(
                $user,
                $response->getMessageId(),
                $chatId,
                $photo->getFileName(),
                Message::DIRECTION_RESPONSE,
                Message::TYPE_NO_ACTION,
                $relatedMessage
            );
        } catch (\Exception $e) {
            throw new TelegramApiException($e->getMessage());
        }
    }

    /**
     * @param int $chatId
     * @param int $messageId
     * @param array $replyMarkup
     * @param string|null $inlineMessageId
     * @return void
     * @throws TelegramApiException
     */
    public function editMessageReplyMarkup(
        int $chatId,
        int $messageId,
        array $replyMarkup,
        ?string $inlineMessageId = null
    )
    {
        try {
            $markup = new InlineKeyboardMarkup($replyMarkup);
            $this->botApi->editMessageReplyMarkup(
                $chatId,
                $messageId,
                $markup,
                $inlineMessageId
            );
        } catch (\Exception $e) {
            throw new TelegramApiException($e->getMessage());
        }
    }

    /**
     * @param User $user
     * @param int $chatId
     * @param string $imageUrl
     * @param string $caption
     * @param Message|null $relatedMessage
     * @param array|null $keyboard
     * @param bool $inline
     * @param string|null $parseMode
     * @throws TelegramApiException
     */
    public function sendImage(
        User     $user,
        int      $chatId,
        string   $imageUrl,
        ?string  $caption = null,
        ?Message $relatedMessage = null,
        ?array   $keyboard = null,
        bool     $inline = false ,
        ?string  $parseMode = 'Markdown'
    ): void
    {
        try {
            if (empty($keyboard)) {
                $markup = null;
            } else {
                $markup = $inline ? new InlineKeyboardMarkup($keyboard) : new ReplyKeyboardMarkup($keyboard, true, true);
            }
            $response = $this->botApi->sendDocument(
                $chatId,
                $imageUrl,
                $caption,
                null,
                $relatedMessage ? $relatedMessage->getMessageId() : null,
                $markup,
                false,
                $parseMode
            );
            $this->messageService->addMessage(
                $user,
                $response->getMessageId(),
                $chatId,
                $imageUrl,
                Message::DIRECTION_RESPONSE,
                Message::TYPE_NO_ACTION,
                $relatedMessage
            );
        } catch (\Exception $e) {
            throw new TelegramApiException($e->getMessage());
        }
    }

    /**
     * @param User $user
     * @param int $chatId
     * @param string $formula
     * @param string|null $caption
     * @param Message|null $relatedMessage
     * @param array|null $keyboard
     * @param string|null $parseMode
     * @throws TelegramApiException
     */
    public function sendFormula(
        User     $user,
        int      $chatId,
        string   $formula,
        ?string  $caption = null,
        ?Message $relatedMessage = null,
        ?array   $keyboard = null,
        ?string  $parseMode = 'Markdown'
    ): void
    {
        try {
            $fileName = FormulaService::getFormulaImage($formula);
            $formulaFile = new CURLFile($fileName, mime_content_type($fileName), basename($fileName));
            $response = $this->botApi->sendPhoto(
                $chatId,
                $formulaFile,
                $caption,
                null,
                $relatedMessage ? $relatedMessage->getMessageId() : null,
                !empty($keyboard) ? new ReplyKeyboardMarkup($keyboard, true, true) : null,
                false,
                $parseMode
            );
            $this->messageService->addMessage(
                $user,
                $response->getMessageId(),
                $chatId,
                $formula,
                Message::DIRECTION_RESPONSE,
                Message::TYPE_NO_ACTION,
                $relatedMessage
            );
        } catch (\Exception $e) {
            throw new TelegramApiException($e->getMessage() . ' (formula: ' . $formula . ')');
        }
    }

    /**
     * @param User $user
     * @param int $chatId
     * @param string $fileName
     * @param string|null $caption
     * @param Message|null $relatedMessage
     * @throws TelegramApiException
     */
    public function sendVoice(
        User     $user,
        int      $chatId,
        string   $fileName,
        ?string  $caption = null,
        ?Message $relatedMessage = null
    ): void
    {
        try {
            $voiceFile = new CURLFile($fileName, mime_content_type($fileName), basename($fileName));
            $response = $this->botApi->sendVoice(
                $chatId,
                $voiceFile,
                $caption,
                null,
                null,
                $relatedMessage ? $relatedMessage->getMessageId() : null
            );
            $this->messageService->addMessage(
                $user,
                $response->getMessageId(),
                $chatId,
                $fileName,
                Message::DIRECTION_RESPONSE,
                Message::TYPE_NO_ACTION,
                $relatedMessage
            );
        } catch (\Exception $e) {
            throw new TelegramApiException($e->getMessage());
        }
    }

    /**
     * @param string $fileId
     * @return string
     * @throws GettingFileErrorException
     */
    public function getFileUrl(string $fileId): string
    {
        try {
            return $this->botApi->getFileUrl() . '/' . $this->botApi->getFile($fileId)->getFilePath();
        } catch (\Exception $e) {
            throw new GettingFileErrorException($e->getMessage() . PHP_EOL . $e->getTraceAsString());
        }
    }

    /**
     * @param LanguageService $lang
     * @param Plan[] $plans
     * @return array
     */
    public function getPlansInlineKeyboard(LanguageService $lang, array $plans): array
    {
        $result = [];
        foreach ($plans as $plan) {
            if (!$plan->getPrice() || $plan->getCurrency() !== Plan::BASE_CURRENCY) {
                continue;
            }
            $result[] = [['text' => $this->getPlanButton($lang, $plan), 'callback_data' => $this->callbackQueryService->encode('plan_list', ['plan_type' => $plan->getType()])]];
        }
        $result[] = [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]];
        return $result;
    }

    /**
     * @param LanguageService $lang
     * @param Plan[] $plans
     * @return array
     */
    public function getPaymentMethodsInlineKeyboard(LanguageService $lang, array $plans): array
    {
        $result = [];
        foreach ($plans as $plan) {
            if (
                !$plan->getPrice() ||
                !in_array($plan->getCurrency(), Plan::ENABLED_CURRENCIES) ||
                empty(Plan::CURRENCY_PROVIDERS[$plan->getCurrency()])
            ) {
                continue;
            }
            foreach (Plan::CURRENCY_PROVIDERS[$plan->getCurrency()] as $provider) {
                $result[] = [[
                    'text' => $this->getPaymentMethodButton($lang, $plan->getPrice(), $plan->getCurrency(), $provider),
                    'callback_data' => $this->callbackQueryService->encode('plan_payment', [
                        'price' => $plan->getPrice(),
                        'currency' => $plan->getCurrency(),
                        'provider' => $provider
                    ])
                ]];
            }
        }
        $result[] = [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]];
        return $result;
    }

    /**
     * @param LanguageService $lang
     * @return array|array[]
     */
    public function getCancelKeyboard(LanguageService $lang): array
    {
        return [[$lang->getLocalizedString('L_CANCEL')]];
    }

    /**
     * @param LanguageService $lang
     * @return array|array[]
     */
    public function getBufferKeyboard(LanguageService $lang): array
    {
        return [[
            $lang->getLocalizedString('L_BUFFER_KEYBOARD_SEND'),
            $lang->getLocalizedString('L_CANCEL')
        ]];
    }

    /**
     * @param LanguageService $lang
     * @return array|array[]
     */
    public function getSystemPromptKeyboard(LanguageService $lang): array
    {
        return [[
            $lang->getLocalizedString('L_SYSTEM_PROMPT_KEYBOARD_RESET'),
            $lang->getLocalizedString('L_CANCEL')
        ]];
    }

    /**
     * @param LanguageService $lang
     * @return array[]
     */
    public function getCreateNewCustomChatKeyboard(LanguageService $lang): array
    {
        return [[
            $lang->getLocalizedString('L_CREATE_NEW_CUSTOM_CHAT_WITHOUT_NAME'),
            $lang->getLocalizedString('L_CANCEL')
        ]];
    }

    /**
     * @param LanguageService $lang
     * @param User $user
     * @param int $page
     * @param int $itemsPerPage
     * @return array
     */
    public function getChatListInlineKeyboard(LanguageService $lang, User $user, int $page, int $itemsPerPage = 5): array
    {
        $result = [];
        $allChats = $this->userChatService->getPaginationChats($user->getId(), $page, $itemsPerPage);
        $totalPages = $this->userChatService->getTotalPages($user->getId(), $itemsPerPage);

        $chatNumberText = $lang->getLocalizedString('L_CUSTOM_CHAT_NUMBER');
        foreach ($allChats as $chat) {
            $text = $chatNumberText . $chat->getChatIndex();
            $text .= $chat->getName() ? ' | ' . $chat->getName() : '';
            $text .= $user->getCurrentChatIndex() === $chat->getChatIndex() ? ' ✅' : '';
            $result[] = [['text' => $text, 'callback_data' => $this->callbackQueryService->encode('select_chat',
                [
                    'chat_index' => $chat->getChatIndex(),
                    'current_page' => $page,
                ]
            )]];
        }

        if ($totalPages !== 1) {
            if ($page === 1) {
                $result[] = [
                    ['text' => "$page/$totalPages", 'callback_data' => $this->callbackQueryService->encode('current_chat')],
                    ['text' => '→', 'callback_data' => $this->callbackQueryService->encode('chat_nav', ['page' => $page, 'direction' => 1])],
                ];
            } elseif ($page === $totalPages) {
                $result[] = [
                    ['text' => '←', 'callback_data' => $this->callbackQueryService->encode('chat_nav', ['page' => $page, 'direction' => -1])],
                    ['text' => "$page/$totalPages", 'callback_data' => $this->callbackQueryService->encode('current_chat')],
                ];
            } else {
                $result[] = [
                    ['text' => '←', 'callback_data' => $this->callbackQueryService->encode('chat_nav', ['page' => $page, 'direction' => -1])],
                    ['text' => "$page/$totalPages", 'callback_data' => $this->callbackQueryService->encode('current_chat')],
                    ['text' => '→', 'callback_data' => $this->callbackQueryService->encode('chat_nav', ['page' => $page, 'direction' => 1])],
                ];
            }
        }

        $result[] = [['text' => $lang->getLocalizedString('L_CUSTOM_CHAT_CREATE'), 'callback_data' => $this->callbackQueryService->encode('create_new_chat')]];

        $result = array_merge_recursive($result, $this->getToolzInlineButtons($lang, $this->userChatService->getOrAddUserChat($user)));

        $result[] = [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]];

        return $result;
    }

    /**
     * @param LanguageService $lang
     * @param string|null $userChatModel
     * @param Model[] $models
     * @return array
     */
    public function getGptModelsInlineKeyboard(LanguageService $lang, ?string $userChatModel, array $models): array
    {
        $result = [];
        foreach ($models as $model) {
            if (!$this->modelService->isGptModel($model->getId())) {
                continue;
            }
            $text = $model->getLabelOrId();

            if (!$model->isAllowed()) {
                $text .= ' 🔒';
                $result[] = [['text' => $text, 'callback_data' => $this->callbackQueryService->encode('not_model', ['model_id' => $model->getId()])]];
                continue;
            }

            if ($userChatModel === $model->getId()) {
                $text .= ' ✅';
            }
            $result[] = [['text' => $text, 'callback_data' => $this->callbackQueryService->encode('chat_models',
                ['model_id' => $model->getId()]
            )]];
        }
        $result[] = [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]];
        return $result;
    }

    /**
     * @param LanguageService $lang
     * @param UserChat $userChat
     * @param Model[] $models
     * @return array
     */
    public function getImageGenerationModelsInlineKeyboard(LanguageService $lang, UserChat $userChat, array $models): array
    {
        $result = [];
        foreach ($models as $model) {
            if (!$this->modelService->isImageGenerationModel($model->getId())) {
                continue;
            }
            $text = $model->getLabelOrId();

            if (!$model->isAllowed()) {
                $text .= ' 🔒';
                $result[] = [['text' => $text, 'callback_data' => $this->callbackQueryService->encode('not_model', ['model_id' => $model->getId()])]];
                continue;
            }

            if ($userChat->getBothubChatModel() === $model->getId()) {
                $text .= ' ✅';
            }
            $result[] = [['text' => $text, 'callback_data' => $this->callbackQueryService->encode('image_models', [
                'model_id' => $model->getId()
            ])]];
        }
        $result[] = [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]];
        return $result;
    }

    /**
     * @param LanguageService $lang
     * @param ButtonDTO[] $mjButtons
     * @return array
     */
    public function getMidjourneyInlineKeyboard(LanguageService $lang, array $mjButtons): array
    {
        $result = [[]];
        foreach ($mjButtons as $button) {
            $result[0][] = ['text' => $button->mjNativeLabel, 'callback_data' => $this->callbackQueryService->encode($button->type, ['id' => $button->id])];
        }
        return $result;
    }

    /**
     * @param LanguageService $lang
     * @param UserChat $userChat
     * @return array
     */
    public function getToolzInlineButtons(LanguageService $lang, UserChat $userChat): array
    {
        $result = [];
        foreach (ToolService::getAvailableToolz() as $tool) {
            $text = $lang->getLocalizedString('L_TOOL_' . strtoupper($tool));
            if ($userChat->getBothubChatModel() === $tool) {
                $text .= ' ✅';
            }
            $result[] = [['text' => $text, 'callback_data' => $this->callbackQueryService->encode('tool', ['tool_id' => $tool])]];
        }
        return $result;
    }

    /**
     * @param LanguageService $lang
     * @param UserChat $userChat
     * @return array
     */
    public function getContextInlineKeyboard(LanguageService $lang, UserChat $userChat): array
    {
        $remember = $userChat->isContextRemember();
        return [
            [['text' => $lang->getLocalizedString('L_CONTEXT_ON') . ($remember ? ' ✅' : ''),
                'callback_data' => $this->callbackQueryService->encode('contextOn')
            ]],
            [['text' => $lang->getLocalizedString('L_CONTEXT_OFF') . (!$remember ? ' ✅' : ''),
                'callback_data' => $this->callbackQueryService->encode('contextOff')
            ]],
            [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]]
        ];
    }

    /**
     * @param LanguageService $lang
     * @param UserChat $userChat
     * @return array
     */
    public function getLinksParseInlineKeyboard(LanguageService $lang, UserChat $userChat): array
    {
        $linksParse = $userChat->isLinksParse();
        return [
            [['text' => $lang->getLocalizedString('L_LINKS_PARSING_ON') . ($linksParse ? ' ✅' : ''),
                'callback_data' => $this->callbackQueryService->encode('linksParseOn')
            ]],
            [['text' => $lang->getLocalizedString('L_LINKS_PARSING_OFF') . (!$linksParse ? ' ✅' : ''),
                'callback_data' => $this->callbackQueryService->encode('linksParseOff')
            ]],
            [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]]
        ];
    }

    /**
     * @param LanguageService $lang
     * @param UserChat $userChat
     * @return array
     */
    public function getFormulaToImageInlineKeyboard(LanguageService $lang, UserChat $userChat): array
    {
        $formulaToImage = $userChat->isFormulaToImage();
        return [
            [['text' => $lang->getLocalizedString('L_FORMULA_TO_IMAGE_ON') . ($formulaToImage ? ' ✅' : ''),
                'callback_data' => $this->callbackQueryService->encode('formulaToImageOn')
            ]],
            [['text' => $lang->getLocalizedString('L_FORMULA_TO_IMAGE_OFF') . (!$formulaToImage ? ' ✅' : ''),
                'callback_data' => $this->callbackQueryService->encode('formulaToImageOff')
            ]],
            [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]]
        ];
    }

    /**
     * @param LanguageService $lang
     * @param UserChat $userChat
     * @return array
     */
    public function getAnswerToVoiceInlineKeyboard(LanguageService $lang, UserChat $userChat): array
    {
        $answerToVoice = $userChat->isAnswerToVoice();
        return [
            [['text' => $lang->getLocalizedString('L_ANSWER_TO_VOICE_ON') . ($answerToVoice ? ' ✅' : ''),
                'callback_data' => $this->callbackQueryService->encode('answerToVoiceOn')
            ]],
            [['text' => $lang->getLocalizedString('L_ANSWER_TO_VOICE_OFF') . (!$answerToVoice ? ' ✅' : ''),
                'callback_data' => $this->callbackQueryService->encode('answerToVoiceOff')
            ]],
            [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('cancel')]]
        ];
    }

    /**
     * @param LanguageService $lang
     * @return array|array[]
     */
    public function getListReferralTemplatesInlineKeyboard(LanguageService $lang): array
    {
        return [[[
            'text' => $lang->getLocalizedString('L_CREATE_REFERRAL_PROGRAM'),
            'callback_data' => $this->callbackQueryService->encode('createReferralProgram')
        ]]];
    }

    /**
     * @param LanguageService $lang
     * @param string $templateId
     * @return array|array[]
     */
    public function getCreateReferralProgramInlineKeyboard(LanguageService $lang, string $templateId): array
    {
        return [[[
            'text' => $lang->getLocalizedString('L_CREATE_REFERRAL_PROGRAM'),
            'callback_data' => $this->callbackQueryService->encode('ref_t', ['id' => $templateId])
        ]]];
    }

    public function getWebSearchInlineKeyboard(LanguageService $lang, bool $isWebSearch): array
    {
        return [
            [['text' => $lang->getLocalizedString('L_WEB_SEARCH_ENABLED') . ($isWebSearch ? ' ✅' : ' ❌'), 'callback_data' => $this->callbackQueryService->encode('webSearchChange')]],
            [['text' => $lang->getLocalizedString('L_CANCEL'), 'callback_data' => $this->callbackQueryService->encode('webSearchChange')]]
        ];
    }

    /**
     * @param LanguageService $lang
     * @param string $text
     * @return PaymentDataDTO|null
     */
    public function checkPaymentMethodButton(LanguageService $lang, string $text): ?PaymentDataDTO
    {
        $plans = $this->planService->getAllEnabledPlans();
        foreach ($plans as $plan) {
            $currency = $plan->getCurrency();
            foreach (Plan::CURRENCY_PROVIDERS[$currency] as $provider) {
                if ($text === $this->getPaymentMethodButton($lang, $plan->getPrice(), $currency, $provider)) {
                    return new PaymentDataDTO($plan->getBothubId(), $provider);
                }
            }
        }
        return null;
    }

    /**
     * @param string $alert
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function sendServiceAlert(string $alert): void
    {
        $this->botApi->sendMessage($this->alertsChannelId, $alert, 'Markdown', true);
    }

    /**
     * @param User $user
     */
    public function clearSystemMessages(User $user): void
    {
        foreach ($user->getSystemMessagesToDelete() as $messageId) {
            try {
                $this->botApi->deleteMessage($user->getTgId(), $messageId);
            } catch (\Exception $e) {
            }
        }
        $user->clearSystemMessagesToDelete();
    }

    /**
     * @param LanguageService $lang
     * @param Plan $plan
     * @return string
     */
    private function getPlanButton(LanguageService $lang, Plan $plan): string
    {
        return $lang->getLocalizedString('L_PLANS_KEYBOARD_PLAN', [
            $plan->getType(),
            number_format($plan->getTokens(), 0, '.', ' ')
        ]);
    }

    /**
     * @param LanguageService $lang
     * @param float $price
     * @param string $currency
     * @param string $provider
     * @return string
     */
    private function getPaymentMethodButton(LanguageService $lang, float $price, string $currency, string $provider): string
    {
        return $lang->getLocalizedString('L_PAYMENT_METHODS_KEYBOARD_PAYMENT_METHOD', [
            number_format($price, 0, '.', ' '),
            $currency,
            $provider === Plan::PROVIDER_CRYPTO
                ? $lang->getLocalizedString('L_CRYPTO')
                : $lang->getLocalizedString('L_BANK_CARD')
        ]);
    }
}