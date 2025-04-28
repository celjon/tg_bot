<?php

namespace App\Service;

class FileService
{
    private const BUFFER_DIRECTORY = __DIR__ . '/../../var/files/';

    /**
     * @param string $fileUrl
     * @param string $prefix
     * @return string
     */
    public static function saveTempFile(string $fileUrl, string $prefix): string
    {
        $fileUrlExploded = explode('/', $fileUrl);
        $fileName = sys_get_temp_dir() . '/' . $prefix . $fileUrlExploded[count($fileUrlExploded) - 1];
        file_put_contents($fileName, file_get_contents($fileUrl));
        return $fileName;
    }

    /**
     * @param string $fileUrl
     * @param string $prefix
     * @return string
     */
    public static function saveBufferFile(string $fileUrl, string $prefix): string
    {
        $fileUrlExploded = explode('/', $fileUrl);
        $fileName = $prefix . $fileUrlExploded[count($fileUrlExploded) - 1];
        if (!is_dir(self::BUFFER_DIRECTORY)) {
            mkdir(self::BUFFER_DIRECTORY);
        }
        file_put_contents(self::getFullBufferFilePath($fileName), file_get_contents($fileUrl));
        return $fileName;
    }

    /**
     * @param string $fileName
     * @return string
     */
    public static function getFullBufferFilePath(string $fileName): string
    {
        return self::BUFFER_DIRECTORY . $fileName;
    }

    /**
     * @param string $fileName
     */
    public static function removeBufferFile(string $fileName): void
    {
        unlink(self::getFullBufferFilePath($fileName));
    }

    /**
     * @param array $fileNames
     */
    public static function removeBufferFiles(array $fileNames): void
    {
        foreach ($fileNames as $fileName) {
            self::removeBufferFile($fileName);
        }
    }

    /**
     * @param array|null $buffer
     */
    public static function clearBufferFiles(?array $buffer): void
    {
        if (empty($buffer)) {
            return;
        }
        foreach ($buffer as $bufferMessage) {
            if (!empty($bufferMessage['fileName'])) {
                self::removeBufferFile($bufferMessage['fileName']);
            }
        }
    }
}