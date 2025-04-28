<?php

namespace App\Service;

use App\Entity\UserChat;

class ToolService
{
    /** @var string */
    private const TRANSCRIBE_MODEL = 'transcribe';
    /** @var string */
    private const VOICE_MODEL = 'voice';
    /** @var string */
    private const DEFAULT_TOOL = self::TRANSCRIBE_MODEL;
    /** @var string[] */
    private const AVAILABLE_TOOLZ = [self::TRANSCRIBE_MODEL, self::VOICE_MODEL];

    /**
     * @param UserChat $userChat
     * @return bool
     */
    public static function isTranscribeToolSelected(UserChat $userChat): bool
    {
        return $userChat->getBothubChatModel() === self::TRANSCRIBE_MODEL;
    }

    /**
     * @param UserChat $userChat
     * @return bool
     */
    public static function isVoiceToolSelected(UserChat $userChat): bool
    {
        return $userChat->getBothubChatModel() === self::VOICE_MODEL;
    }

    /**
     * @return string
     */
    public static function getDefaultTool(): string
    {
        return self::DEFAULT_TOOL;
    }

    /**
     * @return string[]
     */
    public static function getAvailableToolz(): array
    {
        return self::AVAILABLE_TOOLZ;
    }

    /**
     * @param string $model
     * @return bool
     */
    public static function isTool(string $model): bool
    {
        return in_array($model, self::getAvailableToolz());
    }
}