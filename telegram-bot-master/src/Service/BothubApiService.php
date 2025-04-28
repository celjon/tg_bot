<?php

namespace App\Service;

use App\Exception\BothubException;
use App\Exception\MidjourneyException;
use App\Exception\ProhibitedContentException;
use App\Exception\WhisperException;
use App\Service\EventSource\Event;
use App\Service\EventSource\EventSource;
use CURLFile;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BothubApiService
{
    /** @var string */
    private const REQUEST_QUERY = '?request_from=telegram&platform=TELEGRAM';
    /** @var int */
    private const METHOD_GET = 0;
    /** @var int */
    private const METHOD_POST = 1;
    /** @var int */
    private const METHOD_PATCH = 2;
    /** @var int  */
    private const METHOD_PUT = 3;
    /** @var string */
    private $apiUrl;
    /** @var string */
    private $botSecretKey;

    /**
     * BothubApiService constructor.
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(
        ParameterBagInterface $parameterBag
    )
    {
        $bothubSettings = $parameterBag->get('bothubSettings');
        $this->apiUrl = $bothubSettings['apiUrl'];
        $this->botSecretKey = $bothubSettings['botSecretKey'];
    }

    /**
     * @param string $tgId
     * @param string $name
     * @param string|null $id
     * @param string|null $invitedBy
     * @return array
     * @throws BothubException
     */
    public function authorize(?string $tgId, string $name, ?string $id = null, ?string $invitedBy = null): array
    {
        $data = ['name' => $name];
        if ($tgId) {
            $data['tg_id'] = $tgId;
        }
        if ($id) {
            $data['id'] = $id;
        }
        if ($invitedBy) {
            $data['invitedBy'] = $invitedBy;
        }
        return $this->makePostRequest('v2/auth/telegram', ['botsecretkey: ' . $this->botSecretKey], $data);
    }

    /**
     * @param string $accessToken
     * @return array
     * @throws Exception
     * @throws BothubException
     */
    public function getUserInfo(string $accessToken): array
    {
        return $this->makeGetRequest('v2/auth/me', ['Authorization: Bearer ' . $accessToken]);
    }

    /**
     * @param string $accessToken
     * @param string $name
     * @return array
     * @throws BothubException
     */
    public function createNewGroup(string $accessToken, string $name): array
    {
        return $this->makePostRequest('v2/group', ['Authorization: Bearer ' . $accessToken], ['name' => $name]);
    }

    /**
     * @param string $accessToken
     * @param string $groupId
     * @param string $name
     * @param string $modelId
     * @return array
     * @throws BothubException
     */
    public function createNewChat(string $accessToken, ?string $groupId, string $name, ?string $modelId = null): array
    {
        $data = ['name' => $name];
        if ($groupId) {
            $data['groupId'] = $groupId;
        }
        if ($modelId) {
            $data['modelId'] = $modelId;
        }
        return $this->makePostRequest('v2/chat', ['Authorization: Bearer ' . $accessToken], $data);
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @return array
     * @throws BothubException
     */
    public function resetContext(string $accessToken, string $chatId): array
    {
        return $this->makePutRequest('v2/chat/' . $chatId . '/clear-context', ['Authorization: Bearer ' . $accessToken]);
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @param string $model
     * @param int|null $maxTokens
     * @param float $temperature
     * @param float $topP
     * @param string $systemPrompt
     * @param float $presencePenalty
     * @param float $frequencyPenalty
     * @param bool $includeContext
     * @return array
     * @throws BothubException
     */
    public function saveChatSettings(
        string $accessToken,
        string $chatId,
        string $model,
        ?int $maxTokens = null,
        bool $includeContext = true,
        string $systemPrompt = '',
        float $temperature = 0.7,
        float $topP = 1.0,
        float $presencePenalty = 0.0,
        float $frequencyPenalty = 0.0
    ): array {
        $data = [
            'model'             => $model,
            'include_context'   => $includeContext,
            'temperature'       => $temperature,
            'top_p'             => $topP,
            'system_prompt'     => $systemPrompt,
            'presence_penalty'  => $presencePenalty,
            'frequency_penalty' => $frequencyPenalty,
        ];
        if ($maxTokens) {
            $data['max_tokens'] = $maxTokens;
        }
        return $this->makePatchRequest('v2/chat/' . $chatId . '/settings', ['Authorization: Bearer ' . $accessToken], $data);
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @param string $model
     * @return array
     * @throws BothubException
     */
    public function saveModel(
        string $accessToken,
        string $chatId,
        string $model
    ): array {
        return $this->makePatchRequest(
            'v2/chat/' . $chatId . '/settings',
            ['Authorization: Bearer ' . $accessToken],
            [
                'model' => $model
            ]
        );
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @return bool
     * @throws BothubException
     */
    public function getWebSearch(
        string $accessToken,
        string $chatId
    ): bool {
        $response = $this->makeGetRequest(
            'v2/chat/' . $chatId . '/settings',
            ['Authorization: Bearer ' . $accessToken]
        );

        if (!isset($response['text'])) {
            return false;
        }

        return $response['text']['enable_web_search'];
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @param bool $value
     * @return array
     * @throws BothubException
     */
    public function enableWebSearch(
        string $accessToken,
        string $chatId,
        bool $value
    ): array {
        return $this->makePatchRequest(
            'v2/chat/' . $chatId . '/settings',
            ['Authorization: Bearer ' . $accessToken],
            [
                'enable_web_search' => $value
            ]
        );
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @param string $parentModel
     * @return array
     * @throws BothubException
     */
    public function updateParentModel(
        string $accessToken,
        string $chatId,
        string $parentModel
    ): array {
        return $this->makePatchRequest(
            'v2/chat/' . $chatId,
            ['Authorization: Bearer ' . $accessToken],
            [
                'modelId' => $parentModel
            ]
        );
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @param string $systemPrompt
     * @return array
     * @throws BothubException
     */
    public function saveSystemPrompt(
        string $accessToken,
        string $chatId,
        string $systemPrompt
    ): array {
        return $this->makePatchRequest(
            'v2/chat/' . $chatId . '/settings',
            ['Authorization: Bearer ' . $accessToken],
            ['system_prompt' => $systemPrompt]
        );
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @param string $message
     * @param CURLFile[] $files
     * @return array
     * @throws BothubException
     * @throws MidjourneyException
     * @throws ProhibitedContentException
     */
    public function sendMessage(
        string $accessToken,
        string $chatId,
        string $message,
        array $files = []
    ): array {
        $result = [];
        $headers = ['Authorization: Bearer ' . $accessToken];
        $postData = [
            'chatId'    => $chatId,
            'message'   => $message,
            'stream'    => false,
        ];
        if (!empty($files)) {
            for ($i = 0; $i < count($files); $i++) {
                $postData['files[' . $i . ']'] = $files[$i];
            }
        }
        try {
            $responseData = $this->makePostRequest(
                'v2/message/send',
                $headers,
                $postData,
                true,
                true
            );
        } catch (Exception $e) {
            throw new BothubException($e->getMessage(), 500);
        }

        $result['response'] = [];
        if (!empty($responseData['content'])) {
            $result['response']['content'] = $responseData['content'];
        }
        if (!empty($responseData['images'])) {
            foreach ($responseData['images'] as $image) {
                if (!empty($image['original']) && !empty($image['original_id']) && $image['status'] === 'DONE') {
                    $attachment = $image;
                    $attachment['file'] = $image['original'];
                    $attachment['file_id'] = $image['original_id'];
                    $attachment['buttons'] = $image['buttons'];
                    $result['response']['attachments'][] = $attachment;
                }
            }
        } elseif (!empty($responseData['attachments'])) {
            $result['response']['attachments'] = $responseData['attachments'];
        }

        $result['tokens'] = (int)$responseData['transaction']['amount'];
        $error = $responseData['job']['error'];

        if ($error) {
            $midjourneyErrorPrefix = 'Error (MIDJOURNEY_ERROR): ';
            if (strpos($error, $midjourneyErrorPrefix) === 0) {
                throw new MidjourneyException(str_replace($midjourneyErrorPrefix, '', $error));
            }
            throw new BothubException($error, 500);
        }

        if (!empty($result['response'])) {
            $prompt = $result['response']['content'];
            $attachments = [];

            if (!empty($data['response']['attachments'])) {
                foreach ($data['response']['attachments'] as $attachment) {
                    if (!empty($attachment['file']) && $attachment['file']['type'] === 'IMAGE') {
                        $url = $attachment->file->url ?? 'https://storage.bothub.chat/bothub-storage/' . $attachment->file->path;
                        $attachments[] = $url;
                    }
                }
            }

            $moderate = $this->moderateResponse($accessToken, $prompt, $attachments);
            if ($moderate['results'][0]['flagged']) {
                $prohibitedContent = implode(', ', array_keys(array_filter($moderate['results'][0]['categories'])));
                throw new ProhibitedContentException($prohibitedContent, 403);
            }
        }

        return $result;
    }

    /**
     * @param string $accessToken
     * @param string $chatId
     * @param string $buttonId
     * @return array
     * @throws BothubException
     * @throws MidjourneyException
     */
    public function clickButton(string $accessToken, string $chatId, string $buttonId): array
    {
        $result = [];
        $needSend = true;
        $notFullData = '';
        $contentReceived = false;
        $tokens = 0;
        $es = new EventSource($this->apiUrl . '/api/v2/chat/' . $chatId . '/stream');
        $es->setCurlOptions([
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 180,
            CURLOPT_BUFFERSIZE => 1024*1024*10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Connection: Keep-Alive',
                'Accept-Encoding: gzip, deflate, br',
                'Accept: text/event-stream',
                'Cache-Control: no-cache',
            ],
            CURLOPT_HEADERFUNCTION => function ($_, $data) use ($es, &$needSend, &$result, $chatId, $buttonId, $accessToken) {
                if ($needSend) {
                    $headers = ['Authorization: Bearer ' . $accessToken];
                    try {
                        $this->makePostRequest('v2/message/button/' . $buttonId . '/click', $headers);
                    } catch (Exception $e) {
                        $result['error'] = $e->getMessage();
                        $es->abort();
                    }
                    $needSend = false;
                }
                return strlen($data);
            }
        ]);
        $es->onMessage(function (Event $event) use ($es, &$result, &$notFullData, &$contentReceived, &$tokens) {
            foreach ($event->data as $data) {
                $decodedData = json_decode($notFullData . $data, true);
                if (json_last_error() !== 0) {
                    $notFullData .= $data;
                    break;
                }
                $notFullData = '';
                if ($decodedData['name'] === 'MESSAGE_UPDATE') {
                    $contentReceived = true;
                    if (!isset($result['response'])) {
                        $result['response'] = [];
                    }
                    if (!empty($decodedData['data']['message']['content'])) {
                        $result['response']['content'] = $decodedData['data']['message']['content'];
                    }
                    if (!empty($decodedData['data']['message']['images'])) {
                        foreach ($decodedData['data']['message']['images'] as $image) {
                            if (!empty($image['original']) && !empty($image['original_id']) && $image['status'] === 'DONE') {
                                $attachment = $image;
                                $attachment['file'] = $image['original'];
                                $attachment['file_id'] = $image['original_id'];
                                $attachment['buttons'] = $image['buttons'];
                                $result['response']['attachments'][] = $attachment;
                            }
                        }
                    } elseif (!empty($decodedData['data']['message']['attachments'])) {
                        $result['response']['attachments'] = $decodedData['data']['message']['attachments'];
                    }
                } elseif ($decodedData['name'] === 'TRANSACTION_CREATE') {
                    $tokens += (int)$decodedData['data']['transaction']['amount'];
                    $result['tokens'] = $tokens;
                    if ($contentReceived) {
                        $es->abort();
                    }
                } elseif ($decodedData['name'] === 'JOB_ERROR') {
                    $result['error'] = $decodedData['data']['job']['error'];
                    $es->abort();
                }
            }
        });
        try {
            $es->connect();
        } catch (Exception $e) {
            throw new BothubException($e->getMessage(), 500);
        }
        if (isset($result['error'])) {
            $midjourneyErrorPrefix = 'Error (MIDJOURNEY_ERROR): ';
            if (strpos($result['error'], $midjourneyErrorPrefix) === 0) {
                throw new MidjourneyException(str_replace($midjourneyErrorPrefix, '', $result['error']));
            }
            throw new BothubException($result['error'], 500);
        }

        return $result;
    }

    /**
     * @return array
     * @throws Exception
     * @throws BothubException
     */
    public function listPlans(): array
    {
        return $this->makeGetRequest('v2/plan/list');
    }

    /**
     * @param string $accessToken
     * @param string $planId
     * @param string $provider
     * @param string|null $presentEmail
     * @param string|null $presentUserId
     * @return array
     * @throws BothubException
     */
    public function buyPlan(
        string $accessToken,
        string $planId,
        string $provider,
        ?string $presentEmail = null,
        ?string $presentUserId = null
    ): array {
        $data = ['provider' => $provider];
        if ($presentEmail) {
            $data['presentEmail'] = $presentEmail;
        } elseif ($presentUserId) {
            $data['presentUserId'] = $presentUserId;
        }
        return $this->makePostRequest('v2/plan/' . $planId . '/buy', ['Authorization: Bearer ' . $accessToken], $data);
    }

    /**
     * @param string $accessToken
     * @return array
     * @throws BothubException
     */
    public function generateTelegramConnectionToken(string $accessToken): array
    {
        return $this->makeGetRequest('v2/auth/telegram-connection-token', ['Authorization: Bearer ' . $accessToken]);
    }

    /**
     * @param string $accessToken
     * @return array
     * @throws BothubException
     */
    public function listModels(string $accessToken): array
    {
        return $this->makeGetRequest('v2/model/list', ['Authorization: Bearer ' . $accessToken]);
    }

    /**
     * @param string $accessToken
     * @param string $locale
     * @return array
     * @throws BothubException
     */
    public function listReferralTemplates(string $accessToken, string $locale): array
    {
        return $this->makeGetRequest('v2/referral-template/list', ['Authorization: Bearer ' . $accessToken], [
            'locale' => $locale
        ]);
    }

    /**
     * @param string $accessToken
     * @param string $templateId
     * @return array
     * @throws BothubException
     */
    public function createReferralProgram(string $accessToken, string $templateId): array
    {
        return $this->makePostRequest('v2/referral', ['Authorization: Bearer ' . $accessToken], [
            'templateId' => $templateId
        ]);
    }

    /**
     * @param string $accessToken
     * @return array
     * @throws BothubException
     */
    public function listReferralPrograms(string $accessToken): array
    {
        return $this->makeGetRequest('v2/referral/list', ['Authorization: Bearer ' . $accessToken]);
    }

    /**
     * @param string $accessToken
     * @param CURLFile $file
     * @param string $method
     * @return string
     * @throws WhisperException
     * @throws Exception
     */
    public function whisper(string $accessToken, CURLFile $file, string $method): string
    {
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-type: multipart/form-data',
        ];
        $postData = [
            'model' => 'whisper-1',
            'file'  => $file,
        ];
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/api/v2/openai/v1/audio/' . $method . self::REQUEST_QUERY);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $json = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new BothubException('Error while requesting Bothub API', 500);
            }
            curl_close($ch);
            $result = json_decode($json, true);
            if (empty($result) || !is_array($result) || isset($result['errors'])) {
                throw new BothubException('Invalid response from Bothub API: ' . $json, 500);
            }
            unlink($file->getFilename());
        } catch (Exception $e) {
            unlink($file->getFilename());
            throw $e;
        }
        if (empty($result['text'])) {
            throw new WhisperException(json_encode($result));
        }
        return $result['text'];
    }

    /**
     * @param string $accessToken
     * @param string $input
     * @param string $model
     * @param string $voice
     * @param string $responseFormat
     * @return string
     * @throws BothubException
     */
    public function speech(
        string $accessToken,
        string $input,
        string $model = 'tts-1',
        string $voice = 'alloy',
        string $responseFormat = 'mp3'
    ): string {
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-type: application/json',
        ];
        $postData = [
            'model' => $model,
            'input' => $input,
            'voice' => $voice,
            'response_format' => $responseFormat,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/api/v2/openai/v1/audio/speech' . self::REQUEST_QUERY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new BothubException('Error while requesting Bothub API', 500);
        }
        curl_close($ch);
        $fileName = sys_get_temp_dir() . '/' . md5($input) . time() . '.mp3';
        file_put_contents($fileName, $data);
        return $fileName;
    }

    /**
     * @param string $accessToken
     * @param string|null $prompt
     * @param array|null $attachments
     * @return array
     * @throws BothubException
     */
    public function moderateResponse(
        string $accessToken,
        ?string $prompt,
        ?array $attachments
    ): array
    {
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-type: application/json',
        ];

        $content = [];
        if ($prompt !== null) {
            $content[] = [
                'type' => 'text',
                'text' => $prompt
            ];
        }
        if ($attachments !== null) {
            foreach ($attachments as $image) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => $image
                ];
            }
        }

        $postData = [
            'model' => 'omni-moderation-latest',
            'input' => $content,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/api/v2/openai/v1/moderations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new BothubException('Error while requesting Bothub API', 500);
        }
        curl_close($ch);
        return json_decode($data, true);
    }

    /**
     * @param string $path
     * @param array $headers
     * @param int $method
     * @param array $data
     * @param bool $asJson
     * @param bool $multipart
     * @param int $timeout
     * @return array
     * @throws BothubException
     */
    private function makeRequest(
        string $path,
        array $headers = [],
        int $method = self::METHOD_GET,
        array $data = [],
        bool $asJson = true,
        bool $multipart = false,
        int $timeout = 10
    ): array {
        $ch = curl_init();
        $url = $this->apiUrl . '/api/' . $path . self::REQUEST_QUERY;
        if ($method === self::METHOD_GET && !empty($data)) {
            foreach ($data as $key => $value) {
                $url .= '&' . $key . '=' . $value;
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        if ($method > self::METHOD_GET) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $asJson ? json_encode($data) : $data);
            if ($asJson) {
                $headers[] = 'Content-type: application/json';
            } elseif ($multipart) {
                $headers[] = 'Content-type: multipart/form-data';
            }
        }
        if ($method === self::METHOD_PATCH) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        }
        if ($method === self::METHOD_PUT) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $json = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new BothubException('Error while requesting Bothub API', 500);
        }
        $curlInfo = curl_getinfo($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($json, true);
        if (!is_array($result)) {
            $result = [];
        }
        if ($httpCode >= 300) {
            if (!is_array($result) || isset($result['errors'])) {
                throw new BothubException('Invalid response from Bothub API: ' . $json, 500);
            }
        }
        if (isset($result['error'])) {
            $message = isset($result['error']['code']) ? $result['error']['code'] : $result['error']['message'];
            throw new BothubException($message, $curlInfo['http_code']);
        }
        return $result;
    }

    /**
     * @param string $path
     * @param array $headers
     * @param array $queryParams
     * @return array
     * @throws BothubException
     */
    private function makeGetRequest(string $path, array $headers = [], array $queryParams = []): array
    {
        return $this->makeRequest($path, $headers, self::METHOD_GET, $queryParams);
    }

    /**
     * @param string $path
     * @param array $headers
     * @param array $postData
     * @param bool $asJson
     * @param bool $multipart
     * @param int $timeout
     * @return array
     * @throws BothubException
     */
    private function makePostRequest(
        string $path,
        array $headers = [],
        array $postData = [],
        bool $asJson = true,
        bool $multipart = false,
        int $timeout = 10
    ): array {
        return $this->makeRequest($path, $headers, self::METHOD_POST, $postData, $asJson, $multipart, $timeout);
    }

    /**
     * @param string $path
     * @param array $headers
     * @param array $postData
     * @param bool $asJson
     * @param bool $multipart
     * @return array
     * @throws BothubException
     */
    private function makePatchRequest(
        string $path,
        array $headers = [],
        array $postData = [],
        bool $asJson = true,
        bool $multipart = false
    ): array {
        return $this->makeRequest($path, $headers, self::METHOD_PATCH, $postData, $asJson, $multipart);
    }

    /**
     * @param string $path
     * @param array $headers
     * @param array $postData
     * @param bool $asJson
     * @param bool $multipart
     * @return array
     * @throws BothubException
     */
    private function makePutRequest(
        string $path,
        array $headers = [],
        array $postData = [],
        bool $asJson = true,
        bool $multipart = false
    ): array {
        return $this->makeRequest($path, $headers, self::METHOD_PUT, $postData, $asJson, $multipart);
    }
}
