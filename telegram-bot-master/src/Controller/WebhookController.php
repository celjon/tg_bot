<?php

namespace App\Controller;

use App\Exception\InvalidWebhookException;
use App\Exception\TelegramUpdateException;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\BothubService;
use App\Service\KeyboardService;
use App\Service\LanguageService;
use App\Service\PresentService;
use App\Service\TgBotService;
use App\Service\UserChatService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{
    /**
     * @Route("tg-webhook", name="tg-webhook", methods={"POST"})
     *
     * @param Request $request
     * @param TgBotService $tgBotService
     * @param EntityManagerInterface $em
     * @param LoggerInterface $logger
     * @return Response
     */
    public function telegramHook(
        Request $request,
        TgBotService $tgBotService,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): Response {
        $response = new Response();
        $em->beginTransaction();
        try {
            $tgBotService->validateWebhook($request->headers->get('x-telegram-bot-api-secret-token'));
            $tgBotService->parseUpdate($request->getContent());
            $em->flush();
            $em->commit();
            return $response;
        } catch(InvalidWebhookException $e) {
            $response->setStatusCode(404);
        } catch(TelegramUpdateException $e) {
            $logger->error($e->getMessage(), ['exception' => $e]);
        } catch(Exception $e) {
            $logger->error($e->getMessage(), ['exception' => $e]);
            $response->setStatusCode(500);
        }
        $em->rollback();
        return $response;
    }

    /**
     * @Route("bothub-webhook", name="bothub-webhook", methods={"POST"})
     *
     * @param Request $request
     * @param TgBotService $tgBotService
     * @param KeyboardService $keyboardService
     * @param PresentService $presentService
     * @param UserChatService $userChatService
     * @param BothubService $bothubService
     * @param MessageRepository $messageRepo
     * @param UserRepository $userRepo
     * @param EntityManagerInterface $em
     * @param ParameterBagInterface $parameterBag
     * @param LoggerInterface $logger
     * @return Response
     */
    public function bothubHook(
        Request $request,
        TgBotService $tgBotService,
        KeyboardService $keyboardService,
        PresentService $presentService,
        UserChatService $userChatService,
        BothubService $bothubService,
        MessageRepository $messageRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        ParameterBagInterface $parameterBag,
        LoggerInterface $logger
    ): Response {
        $response = new Response();
        $em->beginTransaction();
        try {
            $json = $request->getContent();
            if (empty($json)) {
                throw new Exception('Incorrect Bothub webhook data');
            }
            $data = json_decode($json, true);
            if (
                !$request->headers->get('botsecretkey') ||
                $request->headers->get('botsecretkey') !== $parameterBag->get('bothubSettings')['botSecretKey']
            ) {
                throw new Exception('Incorrect Bothub webhook data (incorrect secret key): ' . $json);
            }
            if (empty($data) || empty($data['type'])) {
                throw new Exception('Incorrect Bothub webhook data: ' . $json);
            }
            if ($data['type'] === 'message') {
                if (
                    empty($data['message']) || empty($data['message']['additional_content']) ||
                    empty($data['relatedMessageId']) || empty($data['message']['chat_id']) ||
                    empty($data['message']['additional_content']['content'])
                ) {
                    throw new Exception('Incorrect Bothub webhook data: ' . $json);
                }
                $relatedMessage = $messageRepo->findOneById((int)$data['relatedMessageId']);
                $user = $relatedMessage->getUser();
                if ($userChatService->getOrAddUserChat($user)->getBothubChatId() !== $data['message']['chat_id']) {
                    throw new Exception('Incorrect Bothub webhook data (incorrect chat ID): ' . $json);
                }
                $lang = new LanguageService($user->getLanguageCode());
                $keyboardService->setUser($user);
                $userChat = $userChatService->getUserChat($user);
                $webSearch = $userChat && $bothubService->getWebSearch($userChat);
                $keyboardService->setIsWebSearch($webSearch);
                $keyboard = $keyboardService->getMainKeyboard($lang);
                $chatId = $relatedMessage->getChatId();
                if (!empty($data['message']['additional_content']['imageUrls'])) {
                    foreach ($data['message']['additional_content']['imageUrls'] as $imageUrl) {
                        $tgBotService->sendImage($user, $chatId, $imageUrl, null, $relatedMessage, $keyboard);
                        sleep(1);
                    }
                } else {
                    $logger->error('Bothub webhook error: ' . $json);
                    $content = $lang->getLocalizedString('L_ERROR_UNKNOWN_ERROR');
                    $tgBotService->sendMessage($user, $chatId, $content, $relatedMessage, $keyboard);
                }
            } elseif ($data['type'] === 'merge') {
                if (empty($data['oldId']) || empty($data['newId']) || !isset($data['email'])) {
                    throw new Exception('Incorrect Bothub webhook data: ' . $json);
                }
                $user = $userRepo->findOneByBothubId($data['oldId']);
                if (!$user) {
                    throw new Exception('Incorrect Bothub webhook data (incorrect old user ID): ' . $json);
                }
                $user->setBothubId($data['newId'])->setEmail($data['email'])->setBothubAccessToken(null)->setState(null);
                $lang = new LanguageService($user->getLanguageCode());
                $keyboard = $keyboardService->getMainKeyboard($lang, $user->getCurrentChatIndex());
                $content = $lang->getLocalizedString('L_ACCOUNTS_MERGED', [$user->getEmail()]);
                $tgBotService->sendMessage($user, $user->getTgId(), $content, null, $keyboard);
            } elseif ($data['type'] === 'present') {
                if (empty($data['userId']) || empty($data['tokens']) || empty($data['fromUserId'])) {
                    throw new Exception('Incorrect Bothub webhook data: ' . $json);
                }

                $fromUser = $userRepo->findOneByBothubId($data['fromUserId']);
                if (!$fromUser) {
                    throw new Exception('Incorrect Bothub webhook data (incorrect user ID): ' . $json);
                }

                if ($data['viaEmail']) {
                    $lang = new LanguageService($fromUser->getLanguageCode());
                    $keyboard = $keyboardService->getMainKeyboard($lang);
                    $content = $lang->getLocalizedString('L_PRESENT_DONE_EMAIL');
                } else {
                    $user = $userRepo->findOneByBothubId($data['userId']);
                    if (!$user) {
                        throw new Exception('Incorrect Bothub webhook data (incorrect user ID): ' . $json);
                    }
                    $presentService->add($user, $data['tokens']);

                    $lang = new LanguageService($user->getLanguageCode());

                    $keyboard = $keyboardService->getMainKeyboard($lang);
                    $content = $lang->getLocalizedString('L_PRESENT_DONE');
                    $tgBotService->sendMessage($fromUser, $fromUser->getTgId(), $content, null, $keyboard);
                    $content = $lang->getLocalizedString('L_PRESENT_RESEND_NOTIFICATION');
                }
                $tgBotService->sendMessage($fromUser, $fromUser->getTgId(), $content, null, $keyboard);
            } elseif ($data['type'] === 'presentViaEmail') {
                if (empty($data['userId']) || empty($data['tokens']) || empty($data['fromUserId'])) {
                    throw new Exception('Incorrect Bothub webhook data: ' . $json);
                }
                $fromUser = $userRepo->findOneByBothubId($data['fromUserId']);
                if (!$fromUser) {
                    throw new Exception('Incorrect Bothub webhook data (incorrect user ID): ' . $json);
                }
                $lang = new LanguageService($fromUser->getLanguageCode());
                $keyboard = $keyboardService->getMainKeyboard($lang);
                $content = $lang->getLocalizedString('L_PRESENT_DONE_EMAIL');
                $tgBotService->sendMessage($fromUser, $fromUser->getTgId(), $content, null, $keyboard);
            } else {
                throw new Exception('Incorrect Bothub webhook data: ' . $json);
            }
            $em->flush();
            $em->commit();
            return $response;
        } catch(Exception $e) {
            $em->rollback();
            $logger->error($e->getMessage(), ['exception' => $e]);
            $response = new Response(json_encode([
                "error" => $e->getMessage()
            ]));
            $response->headers->set('Content-Type', 'application/json');
            $response->setStatusCode(400);
        }
        return $response;
    }
}