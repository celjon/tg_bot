<?php

namespace App\Command;

use Exception;
use Gregwar\Tex2png\Tex2png;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestLatexCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('test-latex')->setDescription('Testing converting LaTeX into image')
            ->addArgument('formula', InputArgument::REQUIRED, 'LaTeX formula');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formula = trim($input->getArgument('formula'));
        $filename = sys_get_temp_dir() . '/' . md5($formula) . '.png';
        $formulaImage = Tex2png::create($formula)->saveTo($filename)->generate();
        $error = $formulaImage->error;
        if (!empty($error)) {
            /** @var Exception $error */
            $output->writeln($error->getMessage());
            $output->writeln($error->getTraceAsString());
        } else {
            $output->writeln($filename);
        }
        return 0;
    }
}