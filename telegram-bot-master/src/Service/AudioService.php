<?php

namespace App\Service;

use CURLFile;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Format\Audio\Mp3;

class AudioService
{
    /** @var int */
    private const MAX_AUDIO_FILE_SIZE = 25 * 1024 * 1024;
    /** @var FFMpeg */
    private $ffmpeg;
    /** @var FFProbe */
    private $ffprobe;
    /** @var string */
    private $filesDir;

    /**
     * AudioService constructor.
     */
    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create();
        $this->ffprobe = FFProbe::create();
        $this->filesDir = sys_get_temp_dir() . '/';
    }

    /**
     * @param string $fileName
     * @return string[]
     */
    private function splitToChunks(string $fileName): array
    {
        return [$fileName];
        //В настоящий момент код ниже не имеет смысла, так как телега не даёт скачивать файлы больше 20Мб
        /*$filePath = $this->filesDir . $fileName;
        $fileSize = filesize($filePath);
        if ($fileSize > self::MAX_AUDIO_FILE_SIZE) {
            $fileNameExploded = explode('.', $fileName);
            $duration = $this->ffprobe->format($filePath)->get('duration');
            $fileName1 = $fileNameExploded[0] . '_1.' . $fileNameExploded[1];
            $fileName2 = $fileNameExploded[0] . '_2.' . $fileNameExploded[1];
            file_put_contents($this->filesDir . $fileName1, file_get_contents($filePath));
            file_put_contents($this->filesDir . $fileName2, file_get_contents($filePath));
            $this->ffmpeg->open($this->filesDir . $fileName1)->filters()->clip(
                TimeCode::fromSeconds(0),
                TimeCode::fromSeconds(abs($duration / 2))
            );
            $this->ffmpeg->open($this->filesDir . $fileName2)->filters()->clip(
                TimeCode::fromSeconds(abs($duration / 2)),
                TimeCode::fromSeconds($duration - abs($duration / 2))
            );
            $chunks1 = $this->splitToChunks($fileName1);
            $chunks2 = $this->splitToChunks($fileName2);
            return array_merge($chunks1, $chunks2);
        } else {
            return [$fileName];
        }*/
    }

    /**
     * @param string $videoFileName
     * @return string
     */
    private function convertVideoToAudio(string $videoFileName): string
    {
        $video = $this->ffmpeg->open($this->filesDir . $videoFileName);
        $audioFormat = new Mp3();
        $fileNameExploded = explode('.', $videoFileName);
        $audioFileName = $fileNameExploded[0] . '.mp3';
        $video->save($audioFormat, $this->filesDir . $audioFileName);
        return $audioFileName;
    }

    /**
     * @param string $fileUrl
     * @param bool $video
     * @return CURLFile[]
     */
    public function splitToChunksAndGetCurlFiles(string $fileUrl, bool $video): array
    {
        $fileUrlExploded = explode('/', $fileUrl);
        $fileName = $fileUrlExploded[count($fileUrlExploded) - 1];
        file_put_contents($this->filesDir . $fileName, file_get_contents($fileUrl));
        if ($video) {
            $fileName = $this->convertVideoToAudio($fileName);
        }
        $filesNames = $this->splitToChunks($fileName);
        $result = [];
        foreach ($filesNames as $fileName) {
            $result[] = curl_file_create($this->filesDir . $fileName);
        }
        return $result;
    }
}