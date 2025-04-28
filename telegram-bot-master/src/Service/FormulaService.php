<?php

namespace App\Service;

use Gregwar\Tex2png\Tex2png;

class FormulaService
{
    /** @var string */
    private const LATEX_CHARSET = '[a-zA-Z0-9,\|\'\.\(\)\[\]\+\-\*\^\/\{\}\\_\=\\\pi ]';

    /**
     * @param string $formula
     * @return string
     */
    public static function getFormulaImage(string $formula): string
    {
        $filename = sys_get_temp_dir() . '/' . md5($formula) . '.png';
        $formulaImage = Tex2png::create($formula, 1000)->saveTo($filename)->generate();
        $error = $formulaImage->error;
        if (!empty($error)) {
            throw $error;
        }
        $horizontalPadding = 20;
        $verticalPadding = 40;
        $img = imagecreatefrompng($filename);
        [$width, $height] = getimagesize($filename);
        $newWidth = $width + ($horizontalPadding * 2);
        $newHeight = $height + ($verticalPadding * 2);
        $currentRatio = $newWidth / $newHeight;
        $targetRatio = 16 / 9;
        if ($currentRatio > $targetRatio) {
            $newHeight = ceil($newWidth / $targetRatio);
            $verticalPadding = round(($newHeight - $height) / 2);
        }
        $newImg = imagecreatetruecolor($newWidth, $newHeight);
        $bgColor = imagecolorallocate($newImg, 255, 255, 255);
        imagefill($newImg, 0, 0, $bgColor);
        imagecopy($newImg, $img, $horizontalPadding, $verticalPadding, 0, 0, $width, $height);
        imagepng($newImg, $filename);
        return $filename;
    }

    /**
     * @param string $text
     * @return string|null
     */
    public static function parseFormula(string $text): ?string
    {
        $text = trim($text, "*\\ \n");
        $text = str_replace('\\\\', '\\', $text);
        $text = str_replace('\\_', '_', $text);
        $text = str_replace('\\*', '*', $text);
        if (!preg_match('/^\$' . self::LATEX_CHARSET . '+\$$/', $text)) {
            return null;
        }
        return trim($text, "$ ");
    }

    /**
     * Приводит формулы в тексте к единому формату (между квадратными скобками и без переносов строк)
     * @param string $text
     * @return string
     */
    public static function formatFormulas(string $text): string
    {
        $text = preg_replace('/\\\\\((' . self::LATEX_CHARSET . '{1,4})\\\\\)/', '$1', $text);
        $text = preg_replace(
            '/(\\\\\(|\\\\\[|\$\$|\$)\s*(' . self::LATEX_CHARSET . '{5,})\s*(\\\\\)|\\\\\]|\$\$|\$)/U',
            "\n$$2$\n",
            $text
        );
        return $text;
    }
}